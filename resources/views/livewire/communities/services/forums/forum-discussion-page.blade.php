@php
    /** @var \App\Models\Circles\Circle $circle */
    /** @var \App\Models\Forums\ForumGroup $group */
    /** @var \App\Models\Forums\ForumDiscussion $discussion */
    /** @var string $backUrl */
@endphp
<div class="mx-auto min-h-screen w-4/5 py-10">
    {{-- Transient flag confirmation — shown only to the flagger; the persisted
         flag itself has no visible effect for anyone. Uses x-if (not x-show)
         so nothing renders until fired — no FOUC, and no x-cloak CSS needed
         (this project ships none). --}}
    <div x-data="{ show: false, timer: null }"
         x-on:response-flagged.window="show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 3000)">
        <template x-if="show">
            <div x-transition.opacity
                 class="fixed bottom-6 right-6 z-50 rounded-lg border border-border-muted bg-surface px-4 py-2 text-sm text-main shadow-lg">
                {{ __('forums.response.flag_confirmed') }}
            </div>
        </template>
    </div>

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

        {{-- Responses (the comment thread — $discussion->posts is the forum-facing
             alias for the generic comments relation). Grows into most of the page
             height, scrolling when needed.

             Near-live updates via Tier-0 polling (no websockets): every 10s call
             refreshComments — NOT wire:poll on the root — which re-fetches the
             thread but no-ops while a composer has unsaved text, so a poll never
             wipes what someone is mid-typing. --}}
        <div class="mt-8" wire:poll.10s="refreshComments">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('forums.responses_heading') }}</h2>
            <div class="mt-2 min-h-[40vh] max-h-[65vh] overflow-y-auto rounded-lg border border-border-muted p-5">
                @php($resp = $this->responses)
                @forelse ($resp['roots'] as $root)
                    @include('livewire.communities.services.forums.partials.comment', [
                        'comment' => $root,
                        'level' => 1,
                        'byParent' => $resp['byParent'],
                        'byId' => $resp['byId'],
                        'liked' => $resp['liked'],
                        'pendingAiReview' => $resp['pendingAiReview'],
                    ])
                @empty
                    <p class="text-sm text-muted">{{ __('forums.no_responses') }}</p>
                @endforelse

                {{-- New root response composer (participants only; view-only
                     visitors see the thread but no composer). --}}
                @if ($this->canParticipate)
                    <div class="mt-6 border-t border-border-muted pt-4">
                        <textarea wire:model="newRootContent" rows="3"
                                  placeholder="{{ __('forums.post_response_placeholder') }}"
                                  class="w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted"></textarea>
                        @error('newRootContent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <div class="mt-2 flex justify-end">
                            <button type="button" wire:click="postRoot"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">{{ __('forums.post') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
