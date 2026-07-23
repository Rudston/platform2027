@php
    /** @var \App\Models\Comment $comment */
    // Indentation caps at level 3; deeper replies render flattened at level 3.
    $indentClass = match (min($level, 3)) {
        1 => '',
        2 => 'ml-6',
        default => 'ml-12',
    };
    $children = $byParent[$comment->id] ?? [];
    $isLiked = in_array($comment->id, $liked, true);
    $isOwn = auth()->id() !== null && auth()->id() === $comment->user_id;
    $isEditing = $this->editingCommentId === $comment->id;
    // A tombstoned parent must not leak its author's name via a child's label.
    $parent = $byId[$comment->parent_id] ?? null;
    $parentName = ($parent && ! $parent->is_deleted) ? ($parent->user?->name ?? '—') : __('forums.response.deleted_author');

    // Quarantine (pending AI review) — three-way by viewer. Author sees a
    // warning + full content; a moderator sees a badge + full content; everyone
    // else sees a tombstone (replies below still render). Never applies once the
    // comment is deleted (delete tombstone wins).
    $isPendingAi = ! $comment->is_deleted && array_key_exists($comment->id, $pendingAiReview);
    $showAuthorWarning = $isPendingAi && $isOwn;
    $showModeratorBadge = $isPendingAi && ! $isOwn && $this->canManageThread;
    $showPendingTombstone = $isPendingAi && ! $isOwn && ! $this->canManageThread;
    // Deep link the moderator badge to the specific unresolved AI record.
    $pendingRecordUrl = $showModeratorBadge
        ? \App\Filament\Resources\CommentModerationRecords\CommentModerationRecordResource::getUrl('view', ['record' => $pendingAiReview[$comment->id]], panel: 'admin')
        : null;
@endphp
<div wire:key="comment-{{ $comment->id }}" @class([
    'mt-3 border-l pl-3',
    $indentClass,
    'border-border-muted' => ! $showAuthorWarning,
    'border-red-400 bg-red-50' => $showAuthorWarning,
])>
    <p class="text-xs text-muted">
        @if ($comment->is_deleted)
            <span class="font-medium italic text-muted">{{ __('forums.response.deleted_author') }}</span>
        @else
            <span class="font-medium text-main">{{ $comment->user?->name ?? '—' }}</span>
        @endif
        · {{ $comment->created_at->format('d M Y, H:i') }}
        @if (! $comment->is_deleted && $comment->isEdited())
            <span class="italic">{{ __('forums.edited') }}</span>
        @endif
        @if (! $comment->is_deleted && $comment->pinned && $comment->parent_id === null)
            <span class="ml-1 rounded-full border border-border-muted px-2 py-0.5 text-xs text-muted">{{ __('forums.badge.pinned') }}</span>
        @endif
        {{-- Informational badge for someone who can act — links to the record. --}}
        @if ($showModeratorBadge)
            <a href="{{ $pendingRecordUrl }}" target="_blank"
               class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 hover:bg-amber-200">{{ __('forums.response.pending_badge') }}</a>
        @endif
        {{-- Once nesting is visually flattened (level 4+), keep the context. --}}
        @if ($level >= 4)
            <span class="italic">{{ __('forums.replying_to', ['author' => $parentName]) }}</span>
        @endif
    </p>

    {{-- Body: tombstone placeholder / inline editor / plain content --}}
    @if ($comment->is_deleted)
        <div class="mt-1 text-sm italic text-muted">{{ __('forums.response.deleted') }}</div>
    @elseif ($showPendingTombstone)
        {{-- Quarantined from public view; distinct wording from "[deleted]". --}}
        <div class="mt-1 text-sm italic text-muted">{{ __('forums.response.pending') }}</div>
    @elseif ($isEditing)
        <div class="mt-1" wire:key="edit-composer-{{ $comment->id }}">
            <textarea wire:model="editContent" rows="3"
                      class="w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main"></textarea>
            @error('editContent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="mt-1 flex justify-end gap-2">
                <button type="button" wire:click="cancelEditingComment"
                        class="rounded-lg border border-border-muted px-3 py-1.5 text-xs transition hover:opacity-80">{{ __('ui.cancel') }}</button>
                <button type="button" wire:click="saveComment"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-700">{{ __('forums.response.save') }}</button>
            </div>
        </div>
    @else
        <div class="mt-1 whitespace-pre-line text-sm text-main">{{ $comment->content }}</div>
        {{-- Generic warning to the author — deliberately NOT the AI's reasoning. --}}
        @if ($showAuthorWarning)
            <p class="mt-1 text-xs text-red-600">{{ __('forums.response.pending_author_note') }}</p>
        @endif
    @endif

    {{-- Actions: never on a (deleted or pending) tombstone, nor while editing. --}}
    @if (! $comment->is_deleted && ! $isEditing && ! $showPendingTombstone)
        <div class="mt-1 flex flex-wrap items-center gap-4 text-xs">
            @if ($this->canParticipate)
                <button type="button" wire:click="toggleLike({{ $comment->id }})"
                        class="inline-flex items-center gap-1 {{ $isLiked ? 'text-indigo-600' : 'text-muted hover:text-main' }}">
                    <span aria-hidden="true">{{ $isLiked ? '♥' : '♡' }}</span> {{ $comment->likes_count }}
                </button>
                <button type="button" wire:click="reply({{ $comment->id }})"
                        class="text-indigo-600 hover:underline">{{ __('forums.reply') }}</button>
            @else
                <span class="inline-flex items-center gap-1 text-muted">
                    <span aria-hidden="true">♥</span> {{ $comment->likes_count }}
                </span>
            @endif

            {{-- Edit: author only (NEVER the admin override). --}}
            @if ($isOwn)
                <button type="button" wire:click="startEditingComment({{ $comment->id }})"
                        class="inline-flex items-center gap-1 text-muted hover:text-main"><x-icons.edit class="h-3.5 w-3.5" />{{ __('forums.response.edit') }}</button>
            @endif

            {{-- Delete: author OR circle manager (admin override). --}}
            @if ($isOwn || $this->canManageThread)
                <button type="button" wire:click="deleteComment({{ $comment->id }})"
                        wire:confirm="{{ __('forums.response.delete_confirm') }}"
                        class="text-red-600 hover:underline">{{ __('forums.response.delete') }}</button>
            @endif

            {{-- Hide: moderation — circle manager only (never the author). --}}
            @if ($this->canManageThread)
                <button type="button" wire:click="hideComment({{ $comment->id }})"
                        wire:confirm="{{ __('forums.response.hide_confirm') }}"
                        class="text-muted hover:text-main">{{ __('forums.response.hide') }}</button>
            @endif

            {{-- Flag: any participant, on others' comments only. --}}
            @if ($this->canParticipate && ! $isOwn)
                @if (in_array($comment->id, $this->flaggedByMe, true))
                    <span class="text-muted">{{ __('forums.response.flagged') }}</span>
                @else
                    <button type="button" wire:click="flag({{ $comment->id }})"
                            title="{{ __('forums.response.flag_tooltip') }}"
                            class="text-muted hover:text-main">{{ __('forums.response.flag') }}</button>
                @endif
            @endif
        </div>
    @endif

    {{-- Inline reply composer (only under the currently-open, non-tombstoned comment) --}}
    @if ($this->canParticipate && ! $comment->is_deleted && ! $showPendingTombstone && $this->replyingToId === $comment->id)
        <div class="mt-2" wire:key="reply-composer-{{ $comment->id }}">
            <textarea wire:model="replyContent" rows="3"
                      placeholder="{{ __('forums.reply_placeholder') }}"
                      class="w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted"></textarea>
            @error('replyContent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="mt-1 flex justify-end gap-2">
                <button type="button" wire:click="cancelReply"
                        class="rounded-lg border border-border-muted px-3 py-1.5 text-xs transition hover:opacity-80">{{ __('ui.cancel') }}</button>
                <button type="button" wire:click="postReply"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-700">{{ __('forums.post') }}</button>
            </div>
        </div>
    @endif

    {{-- Nested replies (recursive; indentation caps at level 3) --}}
    @foreach ($children as $child)
        @include('livewire.communities.services.forums.partials.comment', [
            'comment' => $child,
            'level' => $level + 1,
            'byParent' => $byParent,
            'byId' => $byId,
            'liked' => $liked,
            'pendingAiReview' => $pendingAiReview,
        ])
    @endforeach
</div>
