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
            'slug' => isset($data['slug']) ? $this->slugFor($data['slug']) : $group->slug,
            'description' => $data['description'] ?? $group->description,
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
     *     hide_voter_identities?: bool,
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
                'hide_voter_identities' => $data['hide_voter_identities'] ?? true,
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

        if (array_key_exists('options', $data)) {
            $this->guardOptions($data['options']);
        }

        return DB::transaction(function () use ($poll, $question, $data, $shape, $method): Poll {
            // Only touch what the caller actually supplied — array_key_exists,
            // not ??, so `false` and an explicit null both come through.
            $changes = [];

            foreach (['title', 'description', 'qualifying_date', 'opens_at', 'closes_at',
                'allow_response_update', 'hide_voter_identities', 'publish_results'] as $field) {
                if (array_key_exists($field, $data)) {
                    $changes[$field] = $data[$field];
                }
            }

            if (array_key_exists('eligibility', $data)) {
                $changes['eligibility'] = $data['eligibility']->value;
            }

            if ($changes !== []) {
                $poll->update($changes);
            }

            if ($question !== null) {
                $question->update([
                    'text' => $data['prompt'] ?? $question->text,
                    'type' => ($shape ?? $question->type)->value,
                    'tally_method' => ($method ?? $question->tally_method)->value,
                    'require_full_ranking' => $data['require_full_ranking'] ?? $question->require_full_ranking,
                    'rating_scale_id' => $data['rating_scale_id'] ?? $question->rating_scale_id,
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

        if ($qualifyingDate->isFuture()) {
            throw new InvalidArgumentException(
                'A qualifying date may not be in the future: the Electorate is snapshotted at publish, '
                .'so a future cut-off could never be resolved without a scheduled job.'
            );
        }

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
            $poll->update(['status' => PollStatus::Draft->value]);

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
     * accepts the known limitation that it reflects approval AT PUBLISH. That
     * is exactly why the answer is written down here rather than recomputed.
     */
    protected function snapshotElectorate(Poll $poll, Carbon $qualifyingDate): void
    {
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

        // syncWithoutDetaching rather than sync: publishing is guarded to
        // Drafts, so this only ever writes a fresh set, and never silently
        // removes an entitlement.
        $poll->electorate()->syncWithoutDetaching(array_keys($memberIds));
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

        $ballots = $question->responses()
            ->with('items')
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

    /** A poll may be amended only while no one has answered it. */
    protected function guardAmendable(Poll $poll): void
    {
        if ($poll->respondentCount() > 0) {
            throw new RuntimeException(
                "Poll [{$poll->getKey()}] already has responses and can no longer be amended: changing "
                .'the ballot would record people as having voted on something they never saw. Cancel it '
                .'and publish a replacement instead.'
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
