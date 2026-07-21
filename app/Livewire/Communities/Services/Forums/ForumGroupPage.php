<?php

namespace App\Livewire\Communities\Services\Forums;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A forum group's Discussions page: the discussion list (pinned first, then
 * recency) and a gated "+ Create Discussion" action. Detail/replies live on
 * ForumDiscussionPage.
 */
#[Layout('layouts.main')]
class ForumGroupPage extends Component
{
    public Circle $circle;

    public ForumGroup $group;

    /** Where the "back" link returns to — the Forums tab we came from. */
    public string $backUrl;

    public function mount(Circle $circle, ForumGroup $forumGroup): void
    {
        $this->circle = $circle;
        $this->group = $forumGroup;

        // Respect group visibility (managers bypass) — no viewing an
        // internal/private group by direct URL.
        abort_unless(
            $this->circle->isManageableBy(auth()->user())
                || $forumGroup->canView($this->membership(), $this->isVisitor()),
            404,
        );

        // Restore where we came from (?from=…); only accept an internal
        // /communities path (no open redirects). Fall back to the circle page.
        $this->backUrl = $this->resolveBackUrl(request()->query('from'));
    }

    /** The viewer's active membership of this circle (null for guests/non-members). */
    public function membership(): ?CircleMembership
    {
        $user = auth()->user();

        return $user ? $this->circle->activeMembership($user) : null;
    }

    public function isVisitor(): bool
    {
        return $this->membership() === null;
    }

    /** Whether the viewer may start a discussion here (gates the button + modal). */
    #[Computed]
    public function canCreate(): bool
    {
        return $this->group->canCreateDiscussion(auth()->user());
    }

    /**
     * Discussions in this group: pinned first, then most recent.
     *
     * @return Collection<int, ForumDiscussion>
     */
    #[Computed]
    public function discussions(): Collection
    {
        return $this->group->discussions()
            ->with('creator')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();
    }

    /** Detail URL for a discussion, with a ?from= back-link to this page. */
    public function discussionUrl(ForumDiscussion $discussion): string
    {
        return route('communities.forums.discussions.show', [
            'circle' => $this->circle,
            'forumGroup' => $this->group->slug,
            'forumDiscussion' => $discussion->slug,
            'from' => route('communities.forums.show', ['circle' => $this->circle, 'forumGroup' => $this->group->slug], false),
        ]);
    }

    #[On('forum-discussions-changed')]
    public function onDiscussionsChanged(): void
    {
        unset($this->discussions);
    }

    private function resolveBackUrl(mixed $from): string
    {
        if (is_string($from) && str_starts_with($from, '/communities')) {
            return $from;
        }

        return route('communities.show', $this->circle);
    }

    public function render()
    {
        return view('livewire.communities.services.forums.forum-group-page')->title($this->group->name);
    }
}
