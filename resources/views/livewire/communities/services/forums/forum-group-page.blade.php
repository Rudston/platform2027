@php
    /** @var \App\Models\Circles\Circle $circle */
    /** @var \App\Models\Forums\ForumGroup $group */
    /** @var string $backUrl */
@endphp
<div class="mx-auto min-h-screen w-4/5 py-10">
    <a href="{{ $backUrl }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
        {{ __('forums.back_to_forums') }}
    </a>

    <div class="mt-4 rounded-lg border border-border-muted bg-surface p-8 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="text-3xl" aria-hidden="true">💬</span>
            <h1 class="text-2xl font-bold text-main">{{ $group->name }}</h1>
        </div>

        @if ($group->description)
            <p class="mt-3 text-muted">{{ $group->description }}</p>
        @endif

        {{-- Discussions --}}
        <div class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('forums.discussions_heading') }}</h2>
                @if ($this->canCreate)
                    <button type="button"
                            wire:click="$dispatch('openModal', { component: 'communities.services.forums.forum-discussion-modal', arguments: { forumGroupId: {{ $group->id }} } })"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        {{ __('forums.create_discussion') }}
                    </button>
                @endif
            </div>

            <div class="mt-4 divide-y divide-border-muted rounded-lg border border-border-muted">
                @forelse ($this->discussions as $discussion)
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <a href="{{ $this->discussionUrl($discussion) }}" wire:navigate
                               class="font-medium text-main hover:underline">{{ $discussion->title }}</a>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ __('forums.by_author', ['author' => $discussion->creator?->name ?? '—']) }}
                                · {{ $discussion->created_at->format('d M Y') }}
                            </p>
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
                @empty
                    <div class="p-8 text-center text-sm text-muted">{{ __('forums.no_discussions') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Modal host (wire-elements/modal) — used by the Create Discussion modal. --}}
    <livewire:wire-elements-modal />
</div>
