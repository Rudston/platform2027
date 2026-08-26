<?php

namespace App\Livewire\Communities\Services\Polls;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Polls\Poll;
use App\Models\Polls\PollGroup;
use App\Services\Circles\VotingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Polls tab: stats, a search/archive filter, and the group grid. Writes
 * delegate to VotingService; the create/edit form is a separate wire-elements
 * modal.
 *
 * Unlike forums there is no per-group visibility to filter on — a Poll Group is
 * organisational only and never gates anything (docs/adr/0003), so every group
 * in the circle is listed and access is decided by each poll.
 */
class PollServiceContainer extends Component
{
    public Circle $circle;

    public ?CircleMembership $membership = null;

    public bool $isVisitor = false;

    public string $search = '';

    /** all | active | archived (default view = active only). */
    public string $statusFilter = 'active';

    public function mount(Circle $circle, ?CircleMembership $membership = null, bool $isVisitor = false): void
    {
        $this->circle = $circle;
        $this->membership = $membership;
        $this->isVisitor = $isVisitor;
    }

    protected function service(): VotingService
    {
        return app(VotingService::class);
    }

    /** Whether the viewer may create/manage groups and polls here. */
    #[Computed]
    public function canManage(): bool
    {
        return $this->circle->isManageableBy(auth()->user());
    }

    /**
     * Every group in this circle, with its polls counted. One query; the
     * filters below work in memory over this single fetch.
     *
     * @return Collection<int, PollGroup>
     */
    #[Computed]
    public function allGroups(): Collection
    {
        return $this->circle->pollGroups()
            ->withCount('polls')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, PollGroup> */
    #[Computed]
    public function groups(): Collection
    {
        return $this->allGroups()
            ->when(
                $this->search !== '',
                fn (Collection $c) => $c->filter(
                    fn (PollGroup $g) => str_contains(mb_strtolower($g->name), mb_strtolower($this->search)),
                ),
            )
            ->when(
                $this->statusFilter === 'active',
                fn (Collection $c) => $c->filter(fn (PollGroup $g) => ! $g->isArchived()),
            )
            ->when(
                $this->statusFilter === 'archived',
                fn (Collection $c) => $c->filter(fn (PollGroup $g) => $g->isArchived()),
            )
            ->values();
    }

    #[Computed]
    public function totalGroups(): int
    {
        return $this->allGroups()->count();
    }

    #[Computed]
    public function totalPolls(): int
    {
        return (int) $this->allGroups()->sum('polls_count');
    }

    /**
     * How many polls are accepting responses right now. Open-ness is derived
     * from the clock, so this cannot be a status filter in SQL — the candidate
     * set is narrowed to published, non-archived polls and isOpen() decides.
     */
    #[Computed]
    public function openPolls(): int
    {
        return $this->circle->polls()
            ->published()
            ->notArchived()
            ->get(['id', 'status', 'opens_at', 'closes_at'])
            ->filter(fn (Poll $poll): bool => $poll->isOpen())
            ->count();
    }

    public function archive(int $groupId): void
    {
        $group = $this->circle->pollGroups()->whereKey($groupId)->first();

        if ($group === null || ! $group->isManageableBy(auth()->user())) {
            return;
        }

        $this->service()->archiveGroup($group);
        $this->forgetGroups();
    }

    public function restore(int $groupId): void
    {
        $group = $this->circle->pollGroups()->whereKey($groupId)->first();

        if ($group === null || ! $group->isManageableBy(auth()->user())) {
            return;
        }

        $this->service()->restoreGroup($group);
        $this->forgetGroups();
    }

    public function moveUp(int $groupId): void
    {
        $this->move($groupId, -1);
    }

    public function moveDown(int $groupId): void
    {
        $this->move($groupId, 1);
    }

    /**
     * Swap a group with its neighbour AS DISPLAYED, then persist the whole
     * circle's order. Using the displayed list matters: with the archive filter
     * on, an active group's visible neighbour may not be its neighbour in the
     * full set, and swapping against a hidden row would look like nothing
     * happened.
     */
    private function move(int $groupId, int $delta): void
    {
        if (! $this->canManage) {
            return;
        }

        $visible = $this->groups();
        $index = $visible->search(fn (PollGroup $g): bool => $g->getKey() === $groupId);

        if ($index === false) {
            return;
        }

        $target = $index + $delta;

        if ($target < 0 || $target >= $visible->count()) {
            return;
        }

        // Flip the two in the circle's canonical order and hand the whole
        // sequence to the service, which owns the write.
        $ordered = $this->allGroups()->pluck('id')->all();
        $a = array_search($groupId, $ordered, true);
        $b = array_search($visible[$target]->getKey(), $ordered, true);

        if ($a === false || $b === false) {
            return;
        }

        [$ordered[$a], $ordered[$b]] = [$ordered[$b], $ordered[$a]];

        $this->service()->reorderGroups($this->circle, $ordered);
        $this->forgetGroups();
    }

    #[On('poll-groups-changed')]
    public function onGroupsChanged(): void
    {
        $this->forgetGroups();
    }

    /**
     * A group's page, with a stateless ?from= back-link carrying ?service=voting
     * so the Polls tab is preselected on return (the same convention as the
     * Forums tab's discussion links).
     */
    public function groupUrl(PollGroup $group): string
    {
        return route('communities.polls.show', [
            'circle' => $this->circle,
            'pollGroup' => $group->slug,
            'from' => route('communities.show', [
                'circle' => $this->circle,
                'service' => $this->service()->getKey(),
            ], false),
        ]);
    }

    private function forgetGroups(): void
    {
        unset($this->allGroups, $this->groups, $this->totalGroups, $this->totalPolls, $this->openPolls);
    }

    public function render()
    {
        return view('livewire.communities.services.polls.poll-service-container');
    }
}
