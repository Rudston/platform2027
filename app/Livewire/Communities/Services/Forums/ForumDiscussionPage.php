<?php

namespace App\Livewire\Communities\Services\Forums;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Comment;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Like;
use App\Services\Circles\ForumService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A forum discussion's detail page: the first post (the discussion's content —
 * editable in place by its author) and the response thread (comments + inline
 * reply composer + like toggle), gated by the group's participation rules. The
 * participant count is derived from contributions (creator ∪ commenters).
 */
#[Layout('layouts.main')]
class ForumDiscussionPage extends Component
{
    public Circle $circle;

    public ForumGroup $group;

    public ForumDiscussion $discussion;

    /** Where "back" returns to — the group's Discussions page we came from. */
    public string $backUrl;

    /** Inline first-post edit (author only). */
    public bool $editingContent = false;

    public string $draftContent = '';

    /** New root response composer (bottom of the thread). */
    public string $newRootContent = '';

    /** The comment whose inline reply composer is open (null = none). */
    public ?int $replyingToId = null;

    public string $replyContent = '';

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

    /** Participants = unique contributors (creator ∪ commenters). */
    #[Computed]
    public function participantCount(): int
    {
        return $this->discussion->participantCount();
    }

    /** Whether the viewer may edit the first post's content (author only). */
    #[Computed]
    public function canEditContent(): bool
    {
        return $this->discussion->canEditContentBy(auth()->user());
    }

    public function startEditingContent(): void
    {
        if (! $this->canEditContent()) {
            return;
        }

        $this->draftContent = $this->discussion->content;
        $this->editingContent = true;
    }

    public function cancelEditingContent(): void
    {
        $this->editingContent = false;
        $this->draftContent = '';
    }

    public function saveContent(): void
    {
        // Re-check server-side (never trust the toggled UI state).
        if (! $this->canEditContent()) {
            $this->editingContent = false;

            return;
        }

        $validated = $this->validate([
            'draftContent' => ['required', 'string', 'max:20000'],
        ]);

        app(ForumService::class)->updateDiscussionContent($this->discussion, $validated['draftContent']);

        $this->discussion->refresh();
        $this->editingContent = false;
        $this->draftContent = '';
    }

    /*
    |--------------------------------------------------------------------------
    | Responses (comments) — display + inline compose + like toggle
    |--------------------------------------------------------------------------
    */

    /**
     * The whole (non-hidden) comment tree for this discussion, loaded ONCE:
     *  - roots: pinned first (by pinned_position), then by created_at asc
     *  - byParent: [parent_id => children in created_at asc]
     *  - byId: keyed lookup (for the "replying to {author}" label)
     *  - liked: comment ids the current user has liked
     *
     * @return array{roots: Collection, byParent: array<int, array>, byId: Collection, liked: array<int, int>}
     */
    #[Computed]
    public function responses(): array
    {
        // Uses the "posts" alias (forum-facing); hidden comments are excluded
        // now (forward-compatible with the deferred moderation step).
        $all = $this->discussion->posts()
            ->where('hidden', false)
            ->with('user')
            ->withCount('likes')   // TODO: batch-load if a discussion grows huge
            ->orderBy('created_at')
            ->get();

        $byParent = [];
        foreach ($all as $c) {
            $byParent[$c->parent_id ?? 0][] = $c;
        }

        $rootBucket = collect($byParent[0] ?? []);
        $roots = $rootBucket->where('pinned', true)->sortBy(fn ($c) => $c->pinned_position ?? PHP_INT_MAX)->values()
            ->concat($rootBucket->where('pinned', false)->values());

        $liked = [];
        if (auth()->check()) {
            $liked = Like::query()
                ->where('likeable_type', (new Comment)->getMorphClass())
                ->where('user_id', auth()->id())
                ->whereIn('likeable_id', $all->pluck('id'))
                ->pluck('likeable_id')
                ->all();
        }

        return ['roots' => $roots, 'byParent' => $byParent, 'byId' => $all->keyBy('id'), 'liked' => $liked];
    }

    /** Post a new root response (bottom composer). Gated by canParticipate. */
    public function postRoot(): void
    {
        if (! $this->canParticipate()) {
            return;
        }

        $data = $this->validate(['newRootContent' => ['required', 'string', 'max:20000']]);

        $this->discussion->comments()->create([
            'user_id' => auth()->id(),
            'content' => $data['newRootContent'],
        ]);

        $this->newRootContent = '';
        // A new comment may add a contributor → refresh the participant count.
        unset($this->responses, $this->participantCount);
    }

    /** Toggle the inline reply composer under a comment (one open at a time). */
    public function reply(int $commentId): void
    {
        $this->replyingToId = $this->replyingToId === $commentId ? null : $commentId;
        $this->replyContent = '';
    }

    public function cancelReply(): void
    {
        $this->replyingToId = null;
        $this->replyContent = '';
    }

    /** Post a reply to the currently-open comment. Gated by canParticipate. */
    public function postReply(): void
    {
        if (! $this->canParticipate() || $this->replyingToId === null) {
            return;
        }

        // The parent must belong to this discussion.
        $parent = $this->discussion->comments()->whereKey($this->replyingToId)->first();
        if ($parent === null) {
            $this->cancelReply();

            return;
        }

        $data = $this->validate(['replyContent' => ['required', 'string', 'max:20000']]);

        $this->discussion->comments()->create([
            'user_id' => auth()->id(),
            'parent_id' => $parent->id,
            'content' => $data['replyContent'],
        ]);

        $this->cancelReply();
        unset($this->responses, $this->participantCount);
    }

    /** Like/unlike a comment for the current user. Gated by canParticipate. */
    public function toggleLike(int $commentId): void
    {
        if (! $this->canParticipate()) {
            return;
        }

        $comment = $this->discussion->comments()->whereKey($commentId)->first();
        if ($comment === null) {
            return;
        }

        $existing = $comment->likes()->where('user_id', auth()->id())->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            // Unique (likeable_type, likeable_id, user_id) backs this — no extra
            // app-level dedupe beyond the create-or-delete on current state.
            $comment->likes()->create(['user_id' => auth()->id()]);
        }

        unset($this->responses);
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
        return view('livewire.communities.services.forums.forum-discussion-page')->title($this->discussion->title);
    }
}
