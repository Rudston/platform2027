<?php

namespace App\Livewire\Communities;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A forum discussion's detail page: the first post (the discussion's content,
 * read-only — no reply composer this phase) plus a Join/Leave control gated by
 * the group's participation rules.
 */
#[Layout('layouts.main')]
class ForumDiscussionPage extends Component
{
    public Circle $circle;

    public ForumGroup $group;

    public ForumDiscussion $discussion;

    /** Where "back" returns to — the group's Discussions page we came from. */
    public string $backUrl;

    public function mount(Circle $circle, ForumGroup $forumGroup, ForumDiscussion $forumDiscussion): void
    {
        $this->circle = $circle;
        $this->group = $forumGroup;
        $this->discussion = $forumDiscussion->load('creator');

        // Respect group visibility (managers bypass) — no viewing an
        // internal/private group's discussion by direct URL.
        abort_unless(
            $this->circle->isManageableBy(auth()->user())
                || $forumGroup->canView($this->membership(), $this->isVisitor()),
            404,
        );

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

    #[Computed]
    public function canParticipate(): bool
    {
        return $this->group->canParticipate($this->membership(), $this->isVisitor());
    }

    #[Computed]
    public function isJoined(): bool
    {
        return $this->discussion->isJoinedBy(auth()->user());
    }

    #[Computed]
    public function participantCount(): int
    {
        return $this->discussion->participantCount();
    }

    public function join(): void
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if ($user === null || ! $this->canParticipate()) {
            return;
        }

        $this->discussion->join($user);
        unset($this->isJoined, $this->participantCount);
    }

    public function leave(): void
    {
        if ($user = auth()->user()) {
            $this->discussion->leave($user);
            unset($this->isJoined, $this->participantCount);
        }
    }

    private function resolveBackUrl(mixed $from): string
    {
        if (is_string($from) && str_starts_with($from, '/communities')) {
            return $from;
        }

        return route('communities.forums.show', ['circle' => $this->circle, 'forumGroup' => $this->group->slug]);
    }

    public function render()
    {
        return view('livewire.communities.forum-discussion-page')->title($this->discussion->title);
    }
}
