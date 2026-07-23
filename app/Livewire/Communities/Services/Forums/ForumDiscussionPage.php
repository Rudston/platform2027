<?php

namespace App\Livewire\Communities\Services\Forums;

use App\Enums\Moderation\ModerationFlagSource;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Comment;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Like;
use App\Models\Moderation\CommentModerationRecord;
use App\Models\User;
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

    /** The comment being edited inline (null = none). */
    public ?int $editingCommentId = null;

    public string $editContent = '';

    /**
     * Comment ids the viewer flagged THIS session — transient feedback only
     * (the persisted flag is invisible to everyone, so flagger feedback must
     * not derive from it). Reset on navigation/reload by design.
     *
     * @var array<int, int>
     */
    public array $flaggedByMe = [];

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

    /**
     * Whether the viewer manages this circle — the admin-override half of a
     * comment's delete gate. Resolved ONCE per render so the recursive comment
     * partial doesn't re-query per comment (the authoritative check is still
     * Comment::canDelete(), re-run server-side in deleteComment()).
     */
    #[Computed]
    public function canManageThread(): bool
    {
        return $this->circle->isManageableBy(auth()->user());
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
     *  - pendingAiReview: [comment_id => moderation_record_id] for quarantined comments
     *
     * @return array{roots: Collection, byParent: array<int, array>, byId: Collection, liked: array<int, int>, pendingAiReview: array<int, int>}
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

        // Quarantine state, batched: ONE query for every comment on the page
        // (the set form of Comment::pendingAiReview()), never per-row. Keyed
        // [comment_id => moderation_record_id] so the moderator badge can deep-link
        // the record (dedupe guarantees at most one pending AI record per comment).
        $pendingAiReview = CommentModerationRecord::query()
            ->pendingAi()
            ->whereIn('comment_id', $all->pluck('id'))
            ->pluck('id', 'comment_id')
            ->all();

        return [
            'roots' => $roots,
            'byParent' => $byParent,
            'byId' => $all->keyBy('id'),
            'liked' => $liked,
            'pendingAiReview' => $pendingAiReview,
        ];
    }

    /**
     * Poll target (wire:poll.10s) for near-live updates — Tier 0 polling, no
     * broadcasting. Forces the comment thread + participant count to re-fetch on
     * the next render (new/edited/tombstoned comments, updated like counts).
     *
     * Guard: while the viewer has a reply OR edit composer open with unsaved
     * content, do nothing — never let a background poll clobber text someone is
     * mid-typing. (The composer's bound value round-trips on the poll request, so
     * skipping the refresh leaves it untouched.) The comment list still refreshes
     * on subsequent ticks once they stop typing or submit.
     */
    public function refreshComments(): void
    {
        if ($this->replyingToId !== null && filled($this->replyContent)) {
            return;
        }

        if ($this->editingCommentId !== null && filled($this->editContent)) {
            return;
        }

        unset($this->responses, $this->participantCount);
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

    /*
    |--------------------------------------------------------------------------
    | Responses — edit / delete / flag
    |--------------------------------------------------------------------------
    */

    /** Open the inline editor for a comment (author only). */
    public function startEditingComment(int $commentId): void
    {
        $comment = $this->discussion->comments()->whereKey($commentId)->first();

        if ($comment === null || ! $comment->canEditBy(auth()->user())) {
            return;
        }

        $this->editingCommentId = $comment->id;
        $this->editContent = $comment->content;
        // Don't leave a reply composer open alongside the editor.
        $this->replyingToId = null;
    }

    public function cancelEditingComment(): void
    {
        $this->editingCommentId = null;
        $this->editContent = '';
    }

    /** Save an inline comment edit (author only; stamps edited_at if changed). */
    public function saveComment(): void
    {
        if ($this->editingCommentId === null) {
            return;
        }

        /** @var User|null $actor */
        $actor = auth()->user();
        $comment = $this->discussion->comments()->whereKey($this->editingCommentId)->first();

        if ($actor === null || $comment === null || ! $comment->canEditBy($actor)) {
            $this->cancelEditingComment();

            return;
        }

        $data = $this->validate(['editContent' => ['required', 'string', 'max:20000']]);

        $comment->editBy($actor, $data['editContent']);

        $this->cancelEditingComment();
        unset($this->responses);
    }

    /** Delete a comment (author or circle manager). Hard/soft decided in the model. */
    public function deleteComment(int $commentId): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        $comment = $this->discussion->comments()->whereKey($commentId)->first();

        if ($actor === null || $comment === null || ! $comment->canDelete($actor)) {
            return;
        }

        $comment->deleteBy($actor);

        if ($this->editingCommentId === $commentId) {
            $this->cancelEditingComment();
        }

        // Deleting the author's last standing comment drops them from the count.
        unset($this->responses, $this->participantCount);
    }

    /** Hide a comment (moderation — circle manager only). Excludes it + replies. */
    public function hideComment(int $commentId): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        $comment = $this->discussion->comments()->whereKey($commentId)->first();

        if ($actor === null || $comment === null || ! $comment->canModerate($actor)) {
            return;
        }

        $comment->hide($actor);

        if ($this->editingCommentId === $commentId) {
            $this->cancelEditingComment();
        }

        unset($this->responses, $this->participantCount);
    }

    /**
     * Flag a comment as offensive (any participant, on others' comments).
     * Sets the persisted bool once (idempotent) — invisible to everyone else;
     * the flagger gets a transient toast + a session-scoped "Flagged" state.
     */
    public function flag(int $commentId): void
    {
        if (! $this->canParticipate()) {
            return;
        }

        $comment = $this->discussion->comments()->whereKey($commentId)->first();

        if ($comment === null || $comment->is_deleted || $comment->user_id === auth()->id()) {
            return;
        }

        if (! $comment->flagged_as_offensive) {
            $comment->update(['flagged_as_offensive' => true]);
        }

        // Feed the same unified moderation queue the AI checker uses (create or
        // reuse a pending User-sourced record — no duplicate per comment).
        CommentModerationRecord::open($comment, ModerationFlagSource::User);

        if (! in_array($commentId, $this->flaggedByMe, true)) {
            $this->flaggedByMe[] = $commentId;
        }

        $this->dispatch('response-flagged');
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
