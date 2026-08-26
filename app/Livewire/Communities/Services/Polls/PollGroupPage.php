<?php

namespace App\Livewire\Communities\Services\Polls;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Services\Circles\VotingService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A Poll Group's page: the polls it holds, with each one's derived state.
 *
 * There is no view gate here, unlike the forum equivalent: a Poll Group has no
 * visibility of its own (docs/adr/0003). Drafts are the exception — they are
 * an organiser's unfinished work and are hidden from everyone else.
 */
#[Layout('layouts.main')]
class PollGroupPage extends Component
{
    public Circle $circle;

    public PollGroup $group;

    /** Where the "back" link returns to — the Polls tab we came from. */
    public string $backUrl;

    public function mount(Circle $circle, PollGroup $pollGroup): void
    {
        $this->circle = $circle;
        $this->group = $pollGroup;

        $this->backUrl = $this->resolveBackUrl(request()->query('from'));
    }

    public function membership(): ?CircleMembership
    {
        $user = auth()->user();

        return $user ? $this->circle->activeMembership($user) : null;
    }

    public function isVisitor(): bool
    {
        return $this->membership() === null;
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->circle->isManageableBy(auth()->user());
    }

    /**
     * The group's polls, newest first. Drafts are visible only to managers —
     * an unpublished poll is work in progress, not a decision anyone should
     * see. Every other state is public to whoever can reach the page; whether
     * a viewer may RESPOND is a separate question the poll answers.
     *
     * @return Collection<int, Poll>
     */
    #[Computed]
    public function polls(): Collection
    {
        return $this->group->polls()
            ->with(['organiser', 'tags'])
            ->withCount('electorate')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (Poll $poll): bool => ! $poll->isDraft() || $this->canManage)
            ->values();
    }

    /** Live respondent counts for the listed polls: one query, not one per row. */
    #[Computed]
    public function respondentCounts(): array
    {
        $pollIds = $this->polls()->pluck('id')->all();

        if ($pollIds === []) {
            return [];
        }

        return \App\Models\Polls\PollResponse::query()
            ->join('poll_questions', 'poll_questions.id', '=', 'poll_responses.poll_question_id')
            ->whereIn('poll_questions.poll_id', $pollIds)
            ->groupBy('poll_questions.poll_id')
            ->selectRaw('poll_questions.poll_id as poll_id, count(*) as aggregate')
            ->pluck('aggregate', 'poll_id')
            ->all();
    }

    public function respondentCountFor(Poll $poll): int
    {
        return (int) ($this->respondentCounts()[$poll->getKey()] ?? 0);
    }

    /**
     * A poll's page, with a ?from= back-link to this group page — which itself
     * carries OUR back-link, so the whole trail survives. Without the nesting,
     * returning here from a poll arrives with no ?from= and the next "back"
     * falls through to the community page's default tab.
     */
    public function pollUrl(Poll $poll): string
    {
        return route('communities.polls.poll', [
            'circle' => $this->circle,
            'pollGroup' => $this->group->slug,
            'poll' => $poll->getKey(),
            'from' => route('communities.polls.show', [
                'circle' => $this->circle,
                'pollGroup' => $this->group->slug,
                'from' => $this->backUrl,
            ], false),
        ]);
    }

    #[On('polls-changed')]
    public function onPollsChanged(): void
    {
        unset($this->polls, $this->respondentCounts);
    }

    /**
     * Where "back" goes. An explicit ?from= wins, but only an internal
     * /communities path (no open redirects).
     *
     * The FALLBACK carries ?service= so a visitor who arrived without a trail —
     * a shared link, a bookmark — still lands on the Polls tab rather than
     * whichever tab happens to be first.
     */
    private function resolveBackUrl(mixed $from): string
    {
        if (is_string($from) && str_starts_with($from, '/communities')) {
            return $from;
        }

        return route('communities.show', [
            'circle' => $this->circle,
            'service' => app(VotingService::class)->getKey(),
        ]);
    }

    public function render()
    {
        return view('livewire.communities.services.polls.poll-group-page')->title($this->group->name);
    }
}
