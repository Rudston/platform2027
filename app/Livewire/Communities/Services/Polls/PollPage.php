<?php

namespace App\Livewire\Communities\Services\Polls;

use App\Enums\Polls\PollResponseShape;
use App\Models\Circles\Circle;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Services\Circles\VotingService;
use App\Support\Polls\Mark;
use App\Support\Polls\PollResult;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * A single Poll: the Prompt and its options, the respond form, the Result, and
 * the organiser's lifecycle actions.
 *
 * Every gate here re-checks a model predicate rather than trusting the view —
 * canRespond for the form, canBeEndedBy for conclude/cancel, isManageableBy for
 * publish. The service re-checks canRespond again on write.
 */
#[Layout('layouts.main')]
class PollPage extends Component
{
    public Circle $circle;

    public PollGroup $group;

    public Poll $poll;

    public string $backUrl;

    /** single_choice: the chosen option id. */
    public ?int $choice = null;

    /** ranked_choice: [optionId => rank|''] ; rating: [optionId => scalePointId|'']. */
    public array $ranks = [];

    public array $scores = [];

    public string $flash = '';

    public function mount(Circle $circle, PollGroup $pollGroup, Poll $poll): void
    {
        $this->circle = $circle;
        $this->group = $pollGroup;
        $this->poll = $poll;

        // A Draft is an organiser's unfinished work — invisible to everyone
        // else, exactly as on the group listing.
        abort_if($poll->isDraft() && ! $poll->isManageableBy(auth()->user()), 404);

        // A poll whose window has passed freezes its Result on first read. This
        // is why no scheduled job is needed: closing is derived, and freezing
        // is idempotent so it does not matter who triggers it.
        if ($poll->isClosed() && ! $poll->isCancelled() && ! $poll->hasResult()) {
            $this->poll = $this->service()->freezeResult($poll);
        }

        $this->backUrl = $this->resolveBackUrl(request()->query('from'));
        $this->hydrateExistingResponse();
    }

    protected function service(): VotingService
    {
        return app(VotingService::class);
    }

    #[Computed]
    public function question()
    {
        return $this->poll->question;
    }

    /** @return Collection<int, \App\Models\Polls\PollOption> */
    #[Computed]
    public function options(): Collection
    {
        return $this->question()?->options()->get() ?? new Collection;
    }

    /** The rating scale itself, so the view can ask how it wants to be drawn. */
    #[Computed]
    public function ratingScale()
    {
        return $this->question()?->ratingScale;
    }

    #[Computed]
    public function scalePoints(): Collection
    {
        return $this->question()?->ratingScale?->points()->get() ?? new Collection;
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->poll->isManageableBy(auth()->user());
    }

    /** Tags on this poll — what it is ABOUT, comparable across circles. */
    #[Computed]
    public function tags()
    {
        return $this->poll->tags()->orderBy('name')->get();
    }

    #[Computed]
    public function canManageTags(): bool
    {
        return $this->poll->canBeTaggedBy(auth()->user());
    }

    /**
     * May the ballot still be changed? A poll is editable until its FIRST
     * response — publishing is not the point of no return. Mirrors exactly
     * what VotingService::updatePoll enforces.
     */
    #[Computed]
    public function canAmend(): bool
    {
        return $this->canManage && $this->poll->isAmendable() && ! $this->poll->isCancelled();
    }

    #[Computed]
    public function canEnd(): bool
    {
        return $this->poll->canBeEndedBy(auth()->user());
    }

    #[Computed]
    public function canRespond(): bool
    {
        return $this->poll->canRespond(auth()->user());
    }

    #[Computed]
    public function hasResponded(): bool
    {
        return $this->poll->hasResponded(auth()->user());
    }

    /** Why the form is unavailable, for an honest message rather than silence. */
    #[Computed]
    public function blockedReason(): ?string
    {
        $user = auth()->user();

        return match (true) {
            $this->canRespond => null,
            $user === null => 'not_eligible',
            $this->hasResponded && ! $this->poll->allow_response_update => 'locked',
            ! $this->poll->isOpen() => 'not_open',
            // In the electorate but no longer a member vs never entitled at all
            // — different situations, and a respondent deserves to know which.
            $this->poll->isInElectorate($user) => 'left_circle',
            default => 'not_eligible_late',
        };
    }

    #[Computed]
    public function respondentCount(): int
    {
        return $this->poll->respondentCount();
    }

    /**
     * The frozen Result if there is one; otherwise, for a manager watching a
     * live poll, the running count. Never shown to ordinary members while the
     * poll is open.
     */
    #[Computed]
    public function result(): ?PollResult
    {
        // An OPEN poll's frozen Result can only be stale — frozen during an
        // earlier close, before the poll was amended back into life. Prefer the
        // running count, so a leftover figure can never masquerade as the
        // outcome of a poll still being voted in.
        if ($this->poll->isOpen()) {
            return $this->canManage ? $this->service()->tally($this->poll) : null;
        }

        if ($this->poll->hasResult()) {
            return $this->service()->frozenResult($this->poll);
        }

        return null;
    }

    #[Computed]
    public function optionLabels(): array
    {
        return $this->options()->pluck('label', 'id')->all();
    }

    public function submit(): void
    {
        $question = $this->question();

        if ($question === null) {
            return;
        }

        try {
            /** @var \App\Models\User $user */
            $user = auth()->user();
            $this->service()->respond($this->poll, $user, $this->marksFromForm($question->type));
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->addError('response', $e->getMessage());

            return;
        }

        $this->poll = $this->poll->fresh();
        $this->flash = __('polls.respond.submitted');
        $this->forgetState();
    }

    public function publish(): void
    {
        abort_unless($this->canManage, 403);

        try {
            $this->poll = $this->service()->publish($this->poll);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->addError('lifecycle', $e->getMessage());

            return;
        }

        $this->forgetState();
    }

    public function conclude(): void
    {
        abort_unless($this->canEnd, 403);

        $this->poll = $this->service()->conclude($this->poll);
        $this->forgetState();
    }

    public function cancelPoll(): void
    {
        abort_unless($this->canEnd, 403);

        $this->poll = $this->service()->cancel($this->poll);
        $this->forgetState();
    }

    /** @return list<Mark> */
    private function marksFromForm(PollResponseShape $shape): array
    {
        return match ($shape) {
            PollResponseShape::SingleChoice => $this->choice === null ? [] : [new Mark($this->choice)],

            PollResponseShape::RankedChoice => array_values(array_map(
                fn (int $optionId): Mark => new Mark($optionId, rank: (int) $this->ranks[$optionId]),
                array_keys(array_filter(
                    $this->ranks,
                    fn ($rank): bool => $rank !== '' && $rank !== null,
                )),
            )),

            PollResponseShape::Rating => array_values(array_map(
                fn (int $optionId): Mark => Mark::scoredWithPoint($optionId, (int) $this->scores[$optionId]),
                array_keys(array_filter(
                    $this->scores,
                    fn ($point): bool => $point !== '' && $point !== null,
                )),
            )),
        };
    }

    /** Show a respondent their own answer back — the one Attribution exception. */
    private function hydrateExistingResponse(): void
    {
        $user = auth()->user();

        if ($user === null || $this->question() === null) {
            return;
        }

        $response = $this->question()->responses()->where('user_id', $user->getKey())->with('items')->first();

        if ($response === null) {
            return;
        }

        foreach ($response->items as $item) {
            $this->choice = $item->rank === null && $item->rating_scale_point_id === null
                ? (int) $item->poll_option_id
                : $this->choice;

            if ($item->rank !== null) {
                $this->ranks[(int) $item->poll_option_id] = $item->rank;
            }

            if ($item->rating_scale_point_id !== null) {
                $this->scores[(int) $item->poll_option_id] = (int) $item->rating_scale_point_id;
            }
        }
    }

    /** Re-read the poll after the edit modal saves. */
    #[On('polls-changed')]
    public function onPollChanged(): void
    {
        $this->poll = $this->poll->fresh();
        $this->forgetState();
        unset($this->question, $this->options, $this->scalePoints, $this->optionLabels);
    }

    #[On('tags-changed')]
    public function onTagsChanged(): void
    {
        unset($this->tags);
    }

    private function forgetState(): void
    {
        unset($this->canRespond, $this->hasResponded, $this->blockedReason,
            $this->respondentCount, $this->result, $this->canEnd, $this->canManage,
            $this->canAmend);
    }

    private function resolveBackUrl(mixed $from): string
    {
        if (is_string($from) && str_starts_with($from, '/communities')) {
            return $from;
        }

        // Fallback for a poll reached without a trail: the group page, itself
        // pointed back at the Polls tab so the next "back" does not land on
        // the community page's default tab.
        return route('communities.polls.show', [
            'circle' => $this->circle,
            'pollGroup' => $this->group->slug,
            'from' => route('communities.show', [
                'circle' => $this->circle,
                'service' => $this->service()->getKey(),
            ], false),
        ]);
    }

    public function render()
    {
        return view('livewire.communities.services.polls.poll-page')->title($this->poll->title);
    }
}
