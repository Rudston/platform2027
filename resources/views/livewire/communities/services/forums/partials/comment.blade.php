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
@endphp
<div wire:key="comment-{{ $comment->id }}" class="{{ $indentClass }} border-l border-border-muted pl-3 mt-3">
    <p class="text-xs text-muted">
        <span class="font-medium text-main">{{ $comment->user?->name ?? '—' }}</span>
        · {{ $comment->created_at->format('d M Y, H:i') }}
        @if ($comment->pinned && $comment->parent_id === null)
            <span class="ml-1 rounded-full border border-border-muted px-2 py-0.5 text-xs text-muted">{{ __('forums.badge.pinned') }}</span>
        @endif
        {{-- Once nesting is visually flattened (level 4+), keep the context. --}}
        @if ($level >= 4)
            <span class="italic">{{ __('forums.replying_to', ['author' => $byId[$comment->parent_id]?->user?->name ?? '—']) }}</span>
        @endif
    </p>

    <div class="mt-1 whitespace-pre-line text-sm text-main">{{ $comment->content }}</div>

    <div class="mt-1 flex items-center gap-4 text-xs">
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
    </div>

    {{-- Inline reply composer (only under the currently-open comment) --}}
    @if ($this->canParticipate && $this->replyingToId === $comment->id)
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
        ])
    @endforeach
</div>
