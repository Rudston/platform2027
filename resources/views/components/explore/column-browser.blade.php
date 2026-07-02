@props([
    'communities',
    'selectedType' => null,
    'selectedCircleId' => null,
    'heading' => null,
    'from' => null,
])

@php
    $isLocationMode = $selectedType === null
        || $selectedType === \App\Enums\CommunityType::LocationCommunity->value;

    // True when this column is listing terminal-level results (the bottom of
    // the geographic hierarchy) — used to show the "request a location" button.
    $isTerminalLevel = $isLocationMode
        && $communities->isNotEmpty()
        && (\App\Enums\LocatableType::tryFrom((string) $communities->first()->locatable_type)?->isTerminal() ?? false);

    // Short geographic badge for a location circle.
    $badgeFor = function ($circle) {
        return match (class_basename((string) $circle->locatable_type)) {
            'Country'              => 'Country',
            'Province'            => 'Province',
            'DistrictMunicipality' => 'DM',
            'LocalMunicipality'   => 'Local Municipality',
            'MainPlace'           => 'Main Place',
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
                            @if ($circle->also_here ?? false)
                                <span class="shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">
                                    Also here
                                </span>
                            @endif
                        </span>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                            {{ $badgeFor($circle) }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>

        @if ($isTerminalLevel)
            {{-- TODO: guard this button with auth + permission check --}}
            <div class="border-t border-gray-100 px-4 py-3 text-center">
                <button
                    type="button"
                    wire:click="$dispatch('openModal', { component: 'explore.request-location-modal', arguments: { parentLocationName: @js($heading), parentCircleId: @js($selectedCircleId) } })"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    Your location not listed? Click here to request us to add it
                </button>
            </div>
        @endif
    </div>
@else
    {{-- Non-location types render as cards, each opening the detail modal. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($communities as $circle)
            <livewire:explore.community-card :circle="$circle" :from="$from" :key="'card-'.$circle->id" />
        @endforeach
    </div>
@endif
