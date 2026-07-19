<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Forums\ForumGroup;
use App\Services\Circles\ForumService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Forums tab overview: stats, search/filter and the group grid. Writes delegate
 * to ForumService; the create/edit form is a separate wire-elements modal.
 *
 * $membership is the viewer's active membership of this circle (null visitor);
 * $isVisitor is the convenience boolean. Manage actions are gated via
 * Circle::isManageableBy(auth user).
 */
class ForumServiceContainer extends Component
{
    public Circle $circle;

    public ?CircleMembership $membership = null;

    public bool $isVisitor = false;

    public string $search = '';

    /** all | active | deactivated | archived (default view = active only). */
    public string $statusFilter = 'active';

    public function mount(Circle $circle, ?CircleMembership $membership = null, bool $isVisitor = false): void
    {
        $this->circle = $circle;
        $this->membership = $membership;
        $this->isVisitor = $isVisitor;
    }

    protected function service(): ForumService
    {
        return app(ForumService::class);
    }

    /** Whether the viewer may create/manage groups here (admin/super or this circle's admin). */
    #[Computed]
    public function canManage(): bool
    {
        return $this->circle->isManageableBy(auth()->user());
    }

    /**
     * This circle's groups the viewer may VIEW — the single source for the list
     * and the stats. Visibility is decided by ForumGroup::canView(); managers
     * see everything (they must, to manage it). Fetched once (all statuses).
     *
     * @return Collection<int, ForumGroup>
     */
    #[Computed]
    public function viewableGroups(): Collection
    {
        return $this->circle->forumGroups()
            ->withCount('discussions')
            ->with('tags')
            ->orderBy('name')
            ->get()
            ->filter(fn (ForumGroup $g) => $this->canManage || $g->canView($this->membership, $this->isVisitor))
            ->values();
    }

    /**
     * The viewable groups with the list's search + status filters applied.
     *
     * @return Collection<int, ForumGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return $this->viewableGroups()
            ->when(
                $this->search !== '',
                fn (Collection $c) => $c->filter(
                    fn (ForumGroup $g) => str_contains(mb_strtolower($g->name), mb_strtolower($this->search)),
                ),
            )
            ->when(
                in_array($this->statusFilter, ['active', 'deactivated', 'archived'], true),
                fn (Collection $c) => $c->filter(fn (ForumGroup $g) => $g->status->value === $this->statusFilter),
            )
            ->values();
    }

    /** Total groups the viewer can see in this circle (all statuses). */
    #[Computed]
    public function totalGroups(): int
    {
        return $this->viewableGroups()->count();
    }

    /** Total discussions across the groups the viewer can see. */
    #[Computed]
    public function totalDiscussions(): int
    {
        return (int) $this->viewableGroups()->sum('discussions_count');
    }

    // Create/Edit modals are opened via a Blade $dispatch('openModal', …) in the
    // view (the app-wide wire-elements pattern), not a PHP dispatch — the modal
    // host lives on the parent page and Blade dispatch reaches it reliably.
    // ForumGroupModal re-checks manage authorization in save().

    public function deactivate(int $groupId): void
    {
        if (! $this->canManage) {
            return;
        }

        $group = $this->circle->forumGroups()->whereKey($groupId)->first();

        if ($group) {
            $this->service()->deactivateGroup($group);
            unset($this->viewableGroups, $this->groups, $this->totalGroups, $this->totalDiscussions);
        }
    }

    /** Refresh after a group is created/edited in the modal. */
    #[On('forum-groups-changed')]
    public function onGroupsChanged(): void
    {
        unset($this->viewableGroups, $this->groups, $this->totalGroups, $this->totalDiscussions);
    }

    /**
     * Discussions page URL for a group, with a stateless ?from= back-link (same
     * convention as CommunityCard's "View →"); the back-link carries
     * ?service=forums so the Forums tab is preselected on return.
     */
    public function discussionsUrl(ForumGroup $group): string
    {
        return route('communities.forums.show', [
            'circle' => $this->circle,
            'forumGroup' => $group->slug,
            // Back-link returns to the community page with the Forums tab
            // preselected (?service=forums → CommunityPage's #[Url] activeServiceKey).
            // RELATIVE path — ForumGroupPage::resolveBackUrl only honours /communities/…
            'from' => route('communities.show', ['circle' => $this->circle, 'service' => $this->service()->getKey()], false),
        ]);
    }

    public function render()
    {
        return view('livewire.communities.services.forum-service-container');
    }
}
