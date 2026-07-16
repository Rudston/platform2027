<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Forums\ForumDiscussion;
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
     * Groups for this circle, filtered by search + status, each with a real
     * discussion count.
     *
     * @return Collection<int, ForumGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return $this->circle->forumGroups()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when(
                in_array($this->statusFilter, ['active', 'deactivated', 'archived'], true),
                fn ($q) => $q->where('status', $this->statusFilter),
            )
            ->withCount('discussions')
            ->orderBy('name')
            ->get();
    }

    /** Total groups in this circle (all statuses). */
    #[Computed]
    public function totalGroups(): int
    {
        return $this->circle->forumGroups()->count();
    }

    /** Real total discussions across all of this circle's groups. */
    #[Computed]
    public function totalDiscussions(): int
    {
        return ForumDiscussion::whereHas('group', fn ($q) => $q->where('circle_id', $this->circle->id))->count();
    }

    public function openCreateGroup(): void
    {
        if (! $this->canManage) {
            return;
        }

        $this->dispatch('openModal', component: 'communities.services.forum-group-modal', arguments: [
            'circleId' => $this->circle->id,
        ]);
    }

    public function openEditGroup(int $groupId): void
    {
        if (! $this->canManage) {
            return;
        }

        $this->dispatch('openModal', component: 'communities.services.forum-group-modal', arguments: [
            'circleId' => $this->circle->id,
            'groupId' => $groupId,
        ]);
    }

    public function deactivate(int $groupId): void
    {
        if (! $this->canManage) {
            return;
        }

        $group = $this->circle->forumGroups()->whereKey($groupId)->first();

        if ($group) {
            $this->service()->deactivateGroup($group);
            unset($this->groups, $this->totalGroups);
        }
    }

    /** Refresh after a group is created/edited in the modal. */
    #[On('forum-groups-changed')]
    public function onGroupsChanged(): void
    {
        unset($this->groups, $this->totalGroups, $this->totalDiscussions);
    }

    /**
     * Discussions page URL for a group, with a stateless ?from= back-link (same
     * convention as CommunityCard's "View →"). TODO: include the active tab once
     * the Community Page has #[Url] tab sync.
     */
    public function discussionsUrl(ForumGroup $group): string
    {
        return route('communities.forums.show', [
            'circle' => $this->circle,
            'forumGroup' => $group->slug,
            'from' => route('communities.show', $this->circle),
        ]);
    }

    public function render()
    {
        return view('livewire.communities.services.forum-service-container');
    }
}
