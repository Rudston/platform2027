@php
    /** @var array $breadcrumb */
    /** @var ?string $selectedType */
    /** @var ?string $typeLabel */
@endphp
<nav class="flex flex-wrap items-center gap-1.5 py-2 text-sm" aria-label="Breadcrumb">
    <span aria-hidden="true">📍</span>

    @foreach ($breadcrumb as $crumb)
        @php($isCurrent = $loop->last && ! $typeLabel)

        @if ($isCurrent)
            <span class="font-semibold text-gray-800">{{ $crumb['name'] }}</span>
        @else
            <button
                type="button"
                wire:click="$parent.navigateToBreadcrumb(@js($crumb['id']))"
                class="text-indigo-600 hover:underline"
            >
                {{ $crumb['name'] }}
            </button>
        @endif

        @if (! $loop->last || $typeLabel)
            <span class="text-gray-400" aria-hidden="true">›</span>
        @endif
    @endforeach

    @if ($typeLabel)
        <span class="font-semibold text-gray-800">{{ $typeLabel }}</span>
    @endif
</nav>
