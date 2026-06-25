@props([
    'icon' => '✨',
    'heading' => '',
    'subheading' => '',
    'ctaLabel' => null,
    'ctaAction' => 'startCommunity',
    'belowCount' => 0,
    'belowLabel' => '',
])

<div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
    <div class="text-5xl" aria-hidden="true">{{ $icon }}</div>

    <h2 class="mt-4 text-lg font-semibold text-gray-800">{{ $heading }}</h2>

    @if ($subheading)
        <p class="mt-1 text-sm text-gray-500">{{ $subheading }}</p>
    @endif

    @if ($ctaLabel)
        <button
            type="button"
            wire:click="{{ $ctaAction }}"
            class="mt-5 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
        >
            {{ $ctaLabel }}
        </button>
    @endif

    @if ((int) $belowCount > 0)
        <div class="mx-auto mt-8 max-w-xs border-t border-gray-100 pt-4 text-sm text-gray-500">
            <span class="font-semibold text-gray-700">{{ $belowCount }}</span> {{ $belowLabel }}
            <div class="mt-0.5 text-gray-400">in sub-regions ›</div>
        </div>
    @endif
</div>
