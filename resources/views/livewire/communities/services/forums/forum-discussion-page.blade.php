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
                <span class="text-sm text-muted">👥 {{ __('forums.participant_count', ['count' => $this->participantCount]) }}</span>
            </div>
        </div>

        {{-- First post (author can edit the content in place; no reply composer) --}}
        <div class="mt-6 rounded-lg border border-border-muted p-5">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs text-muted">
                    {{ __('forums.by_author', ['author' => $discussion->creator?->name ?? '—']) }}
                    @if ($discussion->isEdited())
                        <span class="italic">{{ __('forums.edited') }}</span>
                    @endif
                    · {{ $discussion->created_at->format('d M Y, H:i') }}
                </p>
                @if ($this->canEditContent && ! $editingContent)
                    <button type="button" wire:click="startEditingContent"
                            class="inline-flex shrink-0 items-center gap-1 text-xs text-indigo-600 hover:underline">
                        <x-icons.edit class="h-3.5 w-3.5" />{{ __('forums.edit_post') }}
                    </button>
                @endif
            </div>

            @if ($editingContent)
                <div class="mt-3">
                    <textarea wire:model="draftContent" rows="6"
                              class="w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main"></textarea>
                    @error('draftContent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="mt-2 flex justify-end gap-2">
                        <button type="button" wire:click="cancelEditingContent"
                                class="rounded-lg border border-border-muted px-3 py-1.5 text-xs transition hover:opacity-80">{{ __('ui.cancel') }}</button>
                        <button type="button" wire:click="saveContent"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-700">{{ __('forums.save_post') }}</button>
                    </div>
                </div>
            @else
                <div class="mt-3 whitespace-pre-line text-main">{{ $discussion->content }}</div>
            @endif
        </div>

        {{-- Responses — the posts/comments engine renders here (next step).
             Sized to grow into most of the page height, scrolling when needed. --}}
        <div class="mt-8">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('forums.responses_heading') }}</h2>
            <div class="mt-2 min-h-[40vh] max-h-[65vh] overflow-y-auto rounded-lg border border-border-muted p-5">
                <p class="text-sm text-muted">{{ __('forums.responses_placeholder') }}</p>
            </div>
        </div>

        {{-- Join / leave (participant count is shown top-right, in the header) --}}
        <div class="mt-6 flex justify-end">
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
