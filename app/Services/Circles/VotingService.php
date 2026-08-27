<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Enums\Polls\PollEligibility;
use App\Enums\Polls\PollResponseShape;
use App\Enums\Polls\PollStatus;
use App\Enums\Polls\TallyMethod;
use App\Livewire\Communities\Services\Polls\PollServiceContainer;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Models\Polls\PollQuestion;
use App\Models\Polls\PollResponse;
use App\Models\User;
use App\Support\Polls\Ballot;
use App\Support\Polls\Mark;
use App\Support\Polls\PollResult;
use App\Support\Polls\Tally;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * The Polls service. Every state change goes through here — groups, polls,
 * responses, and the freezing of a Result — so the rules live in one place
 * rather than being restated at each call site.
 *
 * The service key stays 'voting' (a stable handle, like content_blocks.key);
 * the user-facing name is "Polls".
 *
 * Authorization is NOT performed here. Callers gate with the model predicates
 * (PollGroup::isManageableBy, Poll::canBeEndedBy, Poll::canRespond) and abort,
 * which is how the forum components do it — EXCEPT where a rule is an
 * invariant rather than a permission, in which case this refuses loudly.
 */
class VotingService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'voting';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function containerComponent(): ?string
    {
        return PollServiceContainer::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Poll groups
    |--------------------------------------------------------------------------
    */

    /** @param  array{name: string, slug?: ?string, description?: ?string, position?: int}  $data */
    public function createGroup(Circle $circle, User $creator, array $data): PollGroup
    {
        return $circle->pollGroups()->create([
            'created_by' => $creator->getKey(),
            'name' => $data['name'],
            'slug' => $this->slugFor($data['slug'] ?? $data['name']),
            'description' => $data['description'] ?? null,
            'position' => $data['position'] ?? 0,
        ]);
    }

    /** @param  array{name?: string, slug?: ?string, description?: ?string, position?: int}  $data */
    public function updateGroup(PollGroup $group, array $data): PollGroup
    {
        $group->update([
            'name' => $data['name'] ?? $group->name,
            // `isset`, so an explicit null keeps the existing slug rather than
            // clearing it. Deliberate, unlike description: both routes bind a
            // group BY slug, so a null one makes its page unreachable and
            // throws while rendering the whole tab. Nothing sends null today
            // (PollGroupModal falls back to the name); whether the column
            // should be NOT NULL is raised by ticket 13.
            'slug' => isset($data['slug']) ? $this->slugFor($data['slug']) : $group->slug,
            // Nullable, and PollGroupModal sends an explicit null for an
            // emptied field — with `??` a description was typeable but never
            // removable.
            'description' => $this->supplied($data, 'description', $group->description),
            'position' => $data['position'] ?? $group->position,
        ]);

        return $group;
    }

    /**
     * File a group away. Its polls stay listed and findable — a Concluded poll
     * is a record of a community decision and archiving a shelf must not hide
     * it. Groups are never deleted (docs/adr/0003).
     */
    public function archiveGroup(PollGroup $group): PollGroup
    {
        $group->update(['archived_at' => now()]);

        return $group;
    }

    public function restoreGroup(PollGroup $group): PollGroup
    {
        $group->update(['archived_at' => null]);

        return $group;
    }

    /**
     * Write a circle's group order, given every group id in the order wanted.
     *
     * Positions are rewritten as a clean 0..N sequence rather than nudged,
     * because they all start at 0 — a scheme that only swapped values would do
     * nothing until something else had already spread them out. Ids not
     * belonging to this circle are ignored, and any group the caller omitted
     * keeps its relative place at the end.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorderGroups(Circle $circle, array $orderedIds): void
    {
        $existing = $circle->pollGroups()
            ->orderBy('position')
            ->orderBy('name')
            ->pluck('id')
            ->all();

        $wanted = array_values(array_filter(
            array_unique(array_map('intval', $orderedIds)),
            fn (int $id): bool => in_array($id, $existing, true),
        ));

        // Anything the caller did not mention keeps its current relative order,
        // appended after what they did.
        $final = array_merge($wanted, array_values(array_diff($existing, $wanted)));

        DB::transaction(function () use ($circle, $final): void {
            foreach ($final as $position => $id) {
                $circle->pollGroups()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Composing a poll
    |--------------------------------------------------------------------------
    */

    /**
     * Compose a Draft: the poll, its single question (the Prompt, Response
     * Shape and Tally Method), and its options.
     *
     * @param  array{
     *     title: string,
     *     description?: ?string,
     *     prompt: string,
     *     shape: PollResponseShape,
     *     tally_method: TallyMethod,
     *     options: list<string>,
     *     eligibility?: PollEligibility,
     *     require_full_ranking?: bool,
     *     rating_scale_id?: ?int,
     *     allow_response_update?: bool,
     *     publish_results?: bool,
     *     opens_at?: ?Carbon,
     *     closes_at?: ?Carbon,
     *     qualifying_date?: ?Carbon,
     * }  $data
     */
    public function createPoll(PollGroup $group, User $organiser, array $data): Poll
    {
        $shape = $data['shape'];
        $method = $data['tally_method'];

        $this->guardPairing($shape, $method);
        $this->guardRatingScale($shape, $data['rating_scale_id'] ?? null);
        $this->guardOptions($data['options'] ?? []);
        $this->guardWindow($data['opens_at'] ?? null, $data['closes_at'] ?? null);

        return DB::transaction(function () use ($group, $organiser, $data, $shape, $method): Poll {
            /** @var Poll $poll */
            $poll = $group->polls()->create([
                'circle_id' => $group->circle_id,
                'created_by' => $organiser->getKey(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'eligibility' => ($data['eligibility'] ?? PollEligibility::Private)->value,
                'qualifying_date' => $data['qualifying_date'] ?? null,
                'allow_response_update' => $data['allow_response_update'] ?? false,
                'publish_results' => $data['publish_results'] ?? false,
                'opens_at' => $data['opens_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
                'status' => PollStatus::Draft->value,
            ]);

            $question = $poll->questions()->create([
                'position' => 0,
                'text' => $data['prompt'],
                'type' => $shape->value,
                'tally_method' => $method->value,
                'require_full_ranking' => $data['require_full_ranking'] ?? false,
                'rating_scale_id' => $data['rating_scale_id'] ?? null,
            ]);

            $this->replaceOptions($question, $data['options']);

            return $poll->fresh();
        });
    }

    /**
     * Amend a Draft. Refuses once anyone has responded, because changing the
     * ballot would leave people recorded as having voted on something they
     * never saw. Un-publishing back to Draft is allowed ONLY while no response
     * exists — after that, the correct act is to Cancel and publish a
     * replacement.
     *
     * @param  array<string,mixed>  $data
     */
    public function updatePoll(Poll $poll, array $data): Poll
    {
        $this->guardAmendable($poll);

        $question = $poll->question;
        $shape = $data['shape'] ?? $question?->type;
        $method = $data['tally_method'] ?? $question?->tally_method;

        if ($shape !== null && $method !== null) {
            $this->guardPairing($shape, $method);
        }

        // createPoll guards this too; an amendment can just as easily leave a
        // rating poll with no scale, or hang a scale off a single-choice one.
        if ($shape !== null) {
            $this->guardRatingScale(
                $shape,
                $this->supplied($data, 'rating_scale_id', $question?->rating_scale_id),
            );
        }

        if (array_key_exists('options', $data)) {
            $this->guardOptions($data['options']);
        }

        // A Poll carries an Electorate from the moment it is published, so from
        // then on its Qualifying Date has to stay resolvable. A Draft's is
        // checked at publish instead, when it starts to mean something.
        $qualifyingDate = $this->supplied($data, 'qualifying_date', $poll->qualifying_date);
        $this->guardQualifyingDate($qualifyingDate, mustExist: ! $poll->isDraft());

        // A datetime-local field cannot express seconds, so DisplayTime::toInput
        // hands the form the stored date truncated to the minute and the form
        // sends that copy back on EVERY save. Taken literally, saving an
        // unrelated edit would shift the stated Qualifying Date up to 59
        // seconds earlier and re-resolve the Electorate for no reason — so the
        // same minute means unchanged, and the stored value is left alone.
        $qualifyingDateMoved = $this->minute($qualifyingDate) !== $this->minute($poll->qualifying_date);

        if (! $qualifyingDateMoved) {
            unset($data['qualifying_date']);
        }

        // Changing who is entitled to respond must actually change who is
        // entitled to respond. The Electorate is snapshotted BECAUSE it cannot
        // be derived afterwards (docs/adr/0002), so moving either of its inputs
        // and leaving the stored set as it was would give the Poll a
        // denominator nothing can reconstruct. A Draft has no Electorate yet;
        // publishing takes the first snapshot.
        //
        // Amendment requires zero responses (guardAmendable), so this
        // disenfranchises nobody who has already acted.
        $resnapshotElectorate = ! $poll->isDraft() && (
            $qualifyingDateMoved
            || $this->supplied($data, 'eligibility', $poll->eligibility) !== $poll->eligibility
        );

        // Against the EFFECTIVE window: an amendment may move either end, or
        // only one of them.
        $this->guardWindow(
            $this->supplied($data, 'opens_at', $poll->opens_at),
            $this->supplied($data, 'closes_at', $poll->closes_at),
        );

        return DB::transaction(function () use (
            $poll, $question, $data, $shape, $method, $resnapshotElectorate
        ): Poll {
            // Only touch what the caller actually supplied — array_key_exists,
            // not ??, so `false` and an explicit null both come through.
            $changes = [];

            foreach (['title', 'description', 'qualifying_date', 'opens_at', 'closes_at',
                'allow_response_update', 'publish_results'] as $field) {
                if (array_key_exists($field, $data)) {
                    $changes[$field] = $data[$field];
                }
            }

            if (array_key_exists('eligibility', $data)) {
                $changes['eligibility'] = $data['eligibility']->value;
            }

            // A frozen Result describes a particular window and set of options.
            // Amending either makes it describe a poll that no longer exists,
            // and freezeResult() never overwrites — so a stale figure would win
            // forever. Safe to discard: amendment requires zero responses, so
            // no real decision is being thrown away.
            $changes['result'] = null;
            $changes['result_frozen_at'] = null;

            $poll->update($changes);

            // Decided above, against the pre-write values; guardQualifyingDate
            // has already established that a published poll has one.
            if ($resnapshotElectorate && $poll->qualifying_date !== null) {
                $this->snapshotElectorate($poll, $poll->qualifying_date);
            }

            if ($question !== null) {
                // rating_scale_id is the one field here an amendment may
                // CLEAR — the compose form sends an explicit null whenever the
                // shape changes (PollModal::updatedShape) — hence supplied().
                // The others keep `??`: a null is meaningless for them (all
                // NOT NULL), and `??` still passes `false`, so the form's
                // shape-change reset of require_full_ranking arrives intact.
                // The `?? $question->…` on type/tally_method is vestigial:
                // $shape/$method were resolved from this same question above,
                // and $question is non-null in this branch.
                $question->update([
                    'text' => $data['prompt'] ?? $question->text,
                    'type' => ($shape ?? $question->type)->value,
                    'tally_method' => ($method ?? $question->tally_method)->value,
                    'require_full_ranking' => $data['require_full_ranking'] ?? $question->require_full_ranking,
                    'rating_scale_id' => $this->supplied($data, 'rating_scale_id', $question->rating_scale_id),
                ]);

                if (array_key_exists('options', $data)) {
                    $this->replaceOptions($question, $data['options']);
                }
            }

            return $poll->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    /**
     * Release a Draft and FIX ITS ELECTORATE. The snapshot happens here, in one
     * pass, and never again: see docs/adr/0002 for why it cannot be derived
     * later. qualifying_date defaults to now and may never be in the future,
     * which is precisely what lets this run at publish with no scheduled job.
     */
    public function publish(Poll $poll, ?Carbon $qualifyingDate = null): Poll
    {
        if (! $poll->isDraft()) {
            throw new RuntimeException("Poll [{$poll->getKey()}] is not a Draft and cannot be published again.");
        }

        if ($poll->question === null) {
            throw new RuntimeException("Poll [{$poll->getKey()}] has no question and cannot be published.");
        }

        $qualifyingDate ??= $poll->qualifying_date ?? now();

        $this->guardQualifyingDate($qualifyingDate, mustExist: true);

        return DB::transaction(function () use ($poll, $qualifyingDate): Poll {
            $poll->update([
                'status' => PollStatus::Published->value,
                'qualifying_date' => $qualifyingDate,
                'opens_at' => $poll->opens_at ?? now(),
            ]);

            $this->snapshotElectorate($poll->fresh(), $qualifyingDate);

            return $poll->fresh();
        });
    }

    /**
     * Return a published poll to Draft. Only while nobody has responded — see
     * guardAmendable. Clears the electorate, which will be taken afresh on the
     * next publish.
     */
    public function unpublish(Poll $poll): Poll
    {
        $this->guardAmendable($poll);

        return DB::transaction(function () use ($poll): Poll {
            $poll->electorate()->detach();

            // Same reasoning as updatePoll: returning to Draft invalidates any
            // Result frozen while the poll was briefly closed.
            $poll->update([
                'status' => PollStatus::Draft->value,
                'result' => null,
                'result_frozen_at' => null,
            ]);

            return $poll->fresh();
        });
    }

    /**
     * End a poll early. It ran, so it has a Result and the decision stands.
     * Stamps closes_at as well as the status, so the clock and the status can
     * never disagree about whether responses are accepted (docs/adr/0001).
     */
    public function conclude(Poll $poll): Poll
    {
        if ($poll->status !== PollStatus::Published) {
            throw new RuntimeException("Only a published poll can be concluded; poll [{$poll->getKey()}] is {$poll->status->value}.");
        }

        $poll->update([
            'status' => PollStatus::Concluded->value,
            'closes_at' => now(),
        ]);

        return $this->freezeResult($poll->fresh());
    }

    /**
     * Void a poll. Its responses must NEVER be tallied, so no Result is
     * frozen and any Result already frozen is cleared — a cancelled poll
     * yielding a winner would be a fake mandate.
     */
    public function cancel(Poll $poll): Poll
    {
        if (in_array($poll->status, [PollStatus::Concluded, PollStatus::Cancelled], true)) {
            throw new RuntimeException("Poll [{$poll->getKey()}] has already ended.");
        }

        $poll->update([
            'status' => PollStatus::Cancelled->value,
            'closes_at' => now(),
            'result' => null,
            'result_frozen_at' => null,
        ]);

        return $poll->fresh();
    }

    public function archivePoll(Poll $poll): Poll
    {
        $poll->update(['archived_at' => now()]);

        return $poll->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | The electorate
    |--------------------------------------------------------------------------
    */

    /**
     * Write the Electorate: everyone who was a member of the circle on the
     * qualifying date and satisfies the poll's eligibility.
     *
     * Membership as of a past date IS derivable, because circle_memberships is
     * append-only. Approval of an internal role is NOT — metadata is mutated
     * in place — so an Internal poll is filtered in PHP through
     * hasApprovedInternalRole(), the one sanctioned way to judge a role, and
     * accepts the known limitation that it reflects approval AS OF THIS CALL —
     * publication, or the amendment that last moved the Qualifying Date or the
     * eligibility rule. That is exactly why the answer is written down here
     * rather than recomputed on read.
     */
    protected function snapshotElectorate(Poll $poll, Carbon $qualifyingDate): void
    {
        // This method REPLACES the stored set (see the sync below), so it must
        // never run on a Poll anyone has answered. Both callers guarantee that;
        // this makes the method safe on its own terms.
        if ($poll->respondentCount() > 0) {
            throw new RuntimeException(
                "Poll [{$poll->getKey()}] already has responses, so its Electorate is fixed: "
                .'re-snapshotting would remove entitlements that have already been exercised.'
            );
        }

        $memberIds = [];

        CircleMembership::query()
            ->where('circle_id', $poll->circle_id)
            ->where('joined_at', '<=', $qualifyingDate)
            ->where(fn ($q) => $q->whereNull('left_at')->orWhere('left_at', '>', $qualifyingDate))
            ->chunkById(500, function ($memberships) use ($poll, &$memberIds): void {
                foreach ($memberships as $membership) {
                    if ($poll->eligibility === PollEligibility::Internal
                        && ! $membership->hasApprovedInternalRole()) {
                        continue;
                    }

                    $memberIds[$membership->user_id] = true;
                }
            });

        // sync, NOT syncWithoutDetaching: the Electorate must EQUAL what the
        // rules produce as of the Qualifying Date. Re-snapshotting after the
        // date moved earlier — or after eligibility narrowed to Internal —
        // otherwise leaves entitlements the stated date denies. Safe only
        // because of the guard above.
        $poll->electorate()->sync(array_keys($memberIds));
    }

    /*
    |--------------------------------------------------------------------------
    | Responding
    |--------------------------------------------------------------------------
    */

    /**
     * Record a Respondent's answer, creating or revising the single row they
     * are allowed. Eligibility is re-checked HERE, at cast time — never at
     * tally time, so nothing is ever removed from a count retroactively.
     *
     * @param  list<Mark>  $marks
     */
    public function respond(Poll $poll, User $user, array $marks): PollResponse
    {
        if (! $poll->canRespond($user)) {
            throw new RuntimeException(
                "User [{$user->getKey()}] may not respond to poll [{$poll->getKey()}] right now: "
                .'the poll must be Open, and they must be in the Electorate, still a member, and '
                .'either not yet responded or permitted to revise.'
            );
        }

        $question = $poll->question;

        if ($question === null) {
            throw new RuntimeException("Poll [{$poll->getKey()}] has no question to answer.");
        }

        $marks = $this->validateMarks($question, $marks);

        return DB::transaction(function () use ($question, $user, $marks): PollResponse {
            /** @var PollResponse $response */
            $response = $question->responses()->updateOrCreate(
                ['user_id' => $user->getKey()],
                ['submitted_at' => now()],
            );

            // A revision replaces the whole answer rather than merging into it.
            $response->items()->delete();

            foreach ($marks as $mark) {
                $response->items()->create([
                    'poll_option_id' => $mark->optionId,
                    'rank' => $mark->rank,
                    'rating_scale_point_id' => $mark->ratingScalePointId,
                ]);
            }

            return $response->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Tallying
    |--------------------------------------------------------------------------
    */

    /**
     * Compute a Result WITHOUT storing it. Safe to call at any time — while a
     * poll is open this is the running count; after it closes, recomputing is
     * how a frozen Result is CHECKED.
     */
    public function tally(Poll $poll): PollResult
    {
        $question = $poll->question;

        if ($question === null) {
            throw new RuntimeException("Poll [{$poll->getKey()}] has no question to tally.");
        }

        $optionIds = $question->options()->pluck('id')->all();

        // items.ratingScalePoint, not items: a Mark carries the point's VALUE, so
        // reading it off each item lazily cost one query PER ITEM, on a path
        // that runs on every view of an open poll and again at freeze.
        $ballots = $question->responses()
            ->with('items.ratingScalePoint')
            ->get()
            ->map(fn (PollResponse $response): Ballot => new Ballot(
                $response->items
                    ->map(fn ($item): Mark => new Mark(
                        optionId: (int) $item->poll_option_id,
                        rank: $item->rank,
                        value: $item->ratingScalePoint?->value,
                    ))
                    ->all(),
            ))
            ->all();

        return Tally::run($question->tally_method, $optionIds, $ballots);
    }

    /**
     * Freeze the Result. IDEMPOTENT and never destructive: an already-frozen
     * Result is returned untouched, because the frozen figure IS the decision
     * and a later recomputation must not be able to replace it.
     *
     * Only a Closed, non-Cancelled poll freezes. Call it on conclude, and on
     * first read after a poll's window passes — both are safe.
     */
    public function freezeResult(Poll $poll): Poll
    {
        if ($poll->hasResult() || ! $poll->isClosed() || $poll->isCancelled()) {
            return $poll;
        }

        $poll->update([
            'result' => $this->tally($poll)->toArray(),
            'result_frozen_at' => now(),
        ]);

        return $poll->fresh();
    }

    /** The frozen Result, or null if this poll has none. */
    public function frozenResult(Poll $poll): ?PollResult
    {
        return $poll->hasResult() ? PollResult::fromArray($poll->result) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Guards and helpers
    |--------------------------------------------------------------------------
    */

    /**
     * The value the caller supplied for $key, or $current when they did not
     * mention it.
     *
     * array_key_exists, NOT `??`: on an amendment path an explicit null is a
     * VALUE — "clear this field" — and must not be read as an omission. That
     * mistake is what let a shape change keep its rating scale, storing a
     * single-choice question carrying one, which guardRatingScale refuses to
     * create. Use this for every nullable field an amendment may clear.
     */
    protected function supplied(array $data, string $key, mixed $current): mixed
    {
        return array_key_exists($key, $data) ? $data[$key] : $current;
    }

    /**
     * A date reduced to the precision a datetime-local field can express, for
     * comparing "did the organiser change this?" without reading a dropped
     * seconds component as an edit.
     */
    protected function minute(?Carbon $date): ?int
    {
        return $date?->copy()->startOfMinute()->getTimestamp();
    }

    /**
     * The Qualifying Date must be resolvable NOW, and a Poll that has already
     * resolved an Electorate from one may never lose it.
     *
     * A date in the future could not be resolved without a scheduled job, and
     * the Electorate is drawn from the membership log as it stood on that date
     * (docs/adr/0002). Removing it from a published Poll would leave a stored
     * Electorate with nothing stating what it was resolved from — the exact
     * disagreement this guard exists to prevent.
     */
    protected function guardQualifyingDate(?Carbon $qualifyingDate, bool $mustExist): void
    {
        if ($qualifyingDate === null) {
            if ($mustExist) {
                throw new InvalidArgumentException(
                    'A poll that has been published must keep a Qualifying Date: it is what its '
                    .'Electorate was resolved from, and the two may never disagree.'
                );
            }

            return;
        }

        if ($qualifyingDate->isFuture()) {
            throw new InvalidArgumentException(
                'A qualifying date may not be in the future: the Electorate is drawn from the '
                .'membership log as it stood then, so a future date could never be resolved '
                .'without a scheduled job.'
            );
        }
    }

    /**
     * A poll may be amended only while no one has answered it. The rule itself
     * lives on Poll::isAmendable() so the UI gates on exactly what this
     * enforces; this is the write-side guard, not a second definition.
     */
    protected function guardAmendable(Poll $poll): void
    {
        if (! $poll->isAmendable()) {
            throw new RuntimeException(
                "Poll [{$poll->getKey()}] already has responses and can no longer be amended: changing "
                .'the ballot would record people as having voted on something they never saw. Cancel it '
                .'and publish a replacement instead.'
            );
        }
    }

    /**
     * A poll must not close before it opens.
     *
     * Without this a mistyped pair produces a poll that is Closed the moment it
     * is published — which then freezes an empty Result, and the poll looks
     * broken in a way that has nothing to do with the times the organiser was
     * actually looking at.
     */
    protected function guardWindow(?Carbon $opensAt, ?Carbon $closesAt): void
    {
        if ($opensAt !== null && $closesAt !== null && $closesAt->lessThanOrEqualTo($opensAt)) {
            throw new InvalidArgumentException(
                'A poll cannot close before it opens: '
                ."closing {$closesAt->toDateTimeString()} is not after opening {$opensAt->toDateTimeString()}."
            );
        }
    }

    protected function guardPairing(PollResponseShape $shape, TallyMethod $method): void
    {
        if (! $shape->allows($method)) {
            throw new InvalidArgumentException(
                "Tally method [{$method->value}] is not legal for response shape [{$shape->value}]."
            );
        }
    }

    protected function guardRatingScale(PollResponseShape $shape, ?int $ratingScaleId): void
    {
        if ($shape === PollResponseShape::Rating && $ratingScaleId === null) {
            throw new InvalidArgumentException('A rating poll needs a rating scale.');
        }

        if ($shape !== PollResponseShape::Rating && $ratingScaleId !== null) {
            throw new InvalidArgumentException('Only a rating poll may carry a rating scale.');
        }
    }

    /** @param  list<string>  $options */
    protected function guardOptions(array $options): void
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('A poll needs at least two options.');
        }
    }

    /**
     * Validate a set of marks against the question's Response Shape, returning
     * them normalised. This is the server-side re-check: the UI constrains
     * what can be submitted, but the rule lives here.
     *
     * @param  list<Mark>  $marks
     * @return list<Mark>
     */
    protected function validateMarks(PollQuestion $question, array $marks): array
    {
        $optionIds = $question->options()->pluck('id')->all();

        foreach ($marks as $mark) {
            if (! in_array($mark->optionId, $optionIds, true)) {
                throw new InvalidArgumentException("Option [{$mark->optionId}] is not on this ballot.");
            }
        }

        return match ($question->type) {
            PollResponseShape::SingleChoice => $this->validateSingleChoice($marks),
            PollResponseShape::RankedChoice => $this->validateRanking($question, $marks, $optionIds),
            PollResponseShape::Rating => $this->validateRating($question, $marks, $optionIds),
        };
    }

    /** @param  list<Mark>  $marks  @return list<Mark> */
    protected function validateSingleChoice(array $marks): array
    {
        if (count($marks) !== 1) {
            throw new InvalidArgumentException('A single-choice response must mark exactly one option.');
        }

        return [new Mark($marks[0]->optionId)];
    }

    /** @param  list<Mark>  $marks  @param  list<int>  $optionIds  @return list<Mark> */
    protected function validateRanking(PollQuestion $question, array $marks, array $optionIds): array
    {
        if ($marks === []) {
            throw new InvalidArgumentException('A ranked response must rank at least one option.');
        }

        $ranks = array_map(fn (Mark $m): ?int => $m->rank, $marks);

        if (in_array(null, $ranks, true) || count(array_unique($ranks)) !== count($ranks)) {
            throw new InvalidArgumentException('Every ranked option needs its own distinct rank.');
        }

        sort($ranks);

        if ($ranks !== range(1, count($ranks))) {
            throw new InvalidArgumentException('Ranks must run 1..N with no gaps.');
        }

        if ($question->require_full_ranking && count($marks) !== count($optionIds)) {
            throw new InvalidArgumentException('This poll requires every option to be ranked.');
        }

        return array_map(fn (Mark $m): Mark => new Mark($m->optionId, rank: $m->rank), $marks);
    }

    /** @param  list<Mark>  $marks  @param  list<int>  $optionIds  @return list<Mark> */
    protected function validateRating(PollQuestion $question, array $marks, array $optionIds): array
    {
        if (count($marks) !== count($optionIds)) {
            throw new InvalidArgumentException('A rating response must score every option.');
        }

        $pointIds = $question->ratingScale?->points()->pluck('id')->all() ?? [];

        foreach ($marks as $mark) {
            if ($mark->ratingScalePointId === null || ! in_array($mark->ratingScalePointId, $pointIds, true)) {
                throw new InvalidArgumentException("Each option must be scored with a point from this poll's rating scale.");
            }
        }

        return array_map(
            fn (Mark $m): Mark => Mark::scoredWithPoint($m->optionId, $m->ratingScalePointId),
            $marks,
        );
    }

    /** @param  list<string>  $labels */
    protected function replaceOptions(PollQuestion $question, array $labels): void
    {
        $question->options()->delete();

        foreach (array_values($labels) as $position => $label) {
            $question->options()->create(['label' => $label, 'position' => $position]);
        }
    }

    public function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /** Whether a group slug already exists in this circle (optionally ignoring one). */
    public function groupSlugExists(Circle $circle, string $slug, ?int $ignoreId = null): bool
    {
        return $circle->pollGroups()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /** Whether a name's slug is already taken in this circle. */
    public function groupSlugTaken(Circle $circle, string $name, ?int $ignoreId = null): bool
    {
        return $this->groupSlugExists($circle, $this->slugFor($name), $ignoreId);
    }
}
