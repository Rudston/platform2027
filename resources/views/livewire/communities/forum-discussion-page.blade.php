@php
    /** @var \App\Models\Circles\Circle $circle */
    /** @var \App\Models\Forums\ForumGroup $group */
    /** @var \App\Models\Forums\ForumDiscussion $discussion */
    /** @var string $backUrl */
@endphp
<div class="mx-auto min-h-screen w-4/5 py-10">
    <a href="{{ $backUrl }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
        {{ __('forums.back_to_discussions') }}
    </a>

    <div class="mt-4 rounded-lg border border-border-muted bg-surface p-8 shadow-sm">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-main">{{ $discussion->title }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $group->name }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                @if ($discussion->is_pinned)
                    <span class="rounded-full border border-border-muted px-2 py-0.5 text-xs text-muted">{{ __('forums.badge.pinned') }}</span>
                @endif
                @if ($discussion->is_locked)
                    <span class="rounded-full border border-border-muted px-2 py-0.5 text-xs text-muted">{{ __('forums.badge.locked') }}</span>
                @endif
            </div>
        </div>

        {{-- First post (read-only; no reply composer this phase) --}}
        <div class="mt-6 rounded-lg border border-border-muted p-5">
            <p class="text-xs text-muted">
                {{ __('forums.by_author', ['author' => $discussion->creator?->name ?? '—']) }}
                · {{ $discussion->created_at->format('d M Y, H:i') }}
            </p>
            <div class="mt-3 whitespace-pre-line text-main">{{ $discussion->content }}</div>
        </div>

        {{-- Participation --}}
        <div class="mt-6 flex items-center justify-between">
            <span class="text-sm text-muted">
                {{ __('forums.participant_count', ['count' => $this->participantCount]) }}
            </span>

            @auth
                @if ($this->isJoined)
                    <button type="button" wire:click="leave"
                            wire:confirm="{{ __('forums.leave_discussion_confirm') }}"
                            class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium transition hover:opacity-80">
                        {{ __('forums.leave_discussion') }}
                    </button>
                @elseif ($this->canParticipate)
                    <button type="button" wire:click="join"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        {{ __('forums.join_discussion') }}
                    </button>
                @endif
            @endauth
        </div>
    </div>
</div>
