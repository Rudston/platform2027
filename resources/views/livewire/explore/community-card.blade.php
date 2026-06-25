@php
    /** @var \App\Models\Circles\Circle $circle */
@endphp
<div class="flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between">
        <span class="text-2xl" aria-hidden="true">{{ $this->icon() }}</span>
        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
            {{ $this->levelBadge() }}
        </span>
    </div>

    <h3 class="mt-2 font-semibold text-gray-800">{{ $circle->name }}</h3>
    <p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ $circle->description }}</p>

    <div class="mt-4 flex items-center justify-between">
        <span class="text-xs text-gray-400">0 members</span>
        <button
            type="button"
            wire:click="$dispatch('openModal', { component: 'explore.community-detail', arguments: { circleId: {{ $circle->id }} } })"
            class="rounded-lg border border-indigo-600 px-3 py-1.5 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50"
        >
            View
        </button>
    </div>
</div>
