@props([
    'communities',
    'selectedType' => null,
    'selectedCircleId' => null,
    'heading' => null,
])

@php
    $isLocationMode = $selectedType === null
        || $selectedType === \App\Enums\CommunityType::LocationCommunity->value;

    // Short geographic badge for a location circle.
    $badgeFor = function ($circle) {
        return match (class_basename((string) $circle->locatable_type)) {
            'Country'              => 'Country',
            'Province'            => 'Province',
            'DistrictMunicipality' => 'DM',
            'LocalMunicipality'   => 'Local Muni',
            'City'                => ($circle->locatable?->metropolis ? 'Metro' : 'City'),
            default               => class_basename((string) $circle->locatable_type),
        };
    };
@endphp

@if ($isLocationMode)
    {{-- File-browser style column: a navigable list that drills down. --}}
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($heading)
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 class="font-semibold text-gray-800">{{ $heading }}</h2>
            </div>
        @endif

        <ul class="divide-y divide-gray-100">
            @foreach ($communities as $circle)
                <li>
                    <button
                        type="button"
                        wire:click="selectCircle({{ $circle->id }})"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-gray-50"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="text-gray-300" aria-hidden="true">▸</span>
                            <span class="truncate text-gray-800">{{ $circle->locatable?->name ?? $circle->name }}</span>
                        </span>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                            {{ $badgeFor($circle) }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
@else
    {{-- Non-location types render as cards, each opening the detail modal. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($communities as $circle)
            <livewire:explore.community-card :circle="$circle" :key="'card-'.$circle->id" />
        @endforeach
    </div>
@endif
