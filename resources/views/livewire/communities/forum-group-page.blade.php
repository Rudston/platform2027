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

        <div class="mt-8 rounded-lg border border-dashed border-border-muted p-10 text-center text-muted">
            {{ __('forums.discussions_coming_soon') }}
        </div>
    </div>
</div>
