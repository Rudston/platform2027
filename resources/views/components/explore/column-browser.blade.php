@props([
    'communities',
    'selectedType' => null,
    'selectedCircleId' => null,
    'heading' => null,
    'from' => null,
    /* Circle ids the viewer actively belongs to — batch-loaded by the parent,
       used only for the card label ("Enter" vs "Visit"). Never queried here. */
    'memberCircleIds' => [],
    /* [circle_id => active member count], batch-loaded by the parent. */
    'memberCounts' => [],
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
            'Country'              => __('geographic.badge_list.country'),
            'Province'            => __('geographic.badge_list.province'),
            'DistrictMunicipality' => __('geographic.badge_list.dm'),
            'LocalMunicipality'   => __('geographic.badge_list.local_municipality'),
            'MainPlace'           => __('geographic.badge_list.main_place'),
            'City'                => ($circle->locatable?->metropolis ? __('geographic.badge_list.metro') : __('geographic.badge_list.city')),
            default               => class_basename((string) $circle->locatable_type),
        };
    };
@endphp

@if ($isLocationMode)
    {{-- File-browser style column: a navigable list that drills down. --}}
    <div class="overflow-hidden rounded-lg border border-border-muted bg-surface shadow-sm">
        @if ($heading)
            <div class="border-b border-border-muted px-4 py-3">
                <h2 class="font-semibold text-main">{{ $heading }}</h2>
            </div>
        @endif

        <ul class="divide-y divide-border-muted">
            @foreach ($communities as $circle)
                <li>
                    <button
                        type="button"
                        wire:click="selectCircle({{ $circle->id }})"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-border-muted"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="text-muted" aria-hidden="true">▸</span>
                            <span class="truncate text-main">{{ $circle->locatable?->name ?? $circle->name }}</span>
                            @if ($circle->also_here ?? false)
                                <span class="shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">
                                    {{ __('explore.also_here') }}
                                </span>
                            @endif
                        </span>
                        <span class="shrink-0 rounded-full bg-border-muted px-2 py-0.5 text-xs font-medium text-muted">
                            {{ $badgeFor($circle) }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>

        @if ($isTerminalLevel)
            {{-- TODO: guard this button with auth + permission check --}}
            <div class="border-t border-border-muted px-4 py-3 text-center">
                <button
                    type="button"
                    wire:click="$dispatch('openModal', { component: 'explore.request-location-modal', arguments: { parentLocationName: @js($heading), parentCircleId: @js($selectedCircleId) } })"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    {{ __('explore.request_location') }}
                </button>
            </div>
        @endif
    </div>
@else
    {{-- Non-location types render as cards, each opening the detail modal. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($communities as $circle)
            <livewire:explore.community-card :circle="$circle" :from="$from" :is-member="in_array($circle->id, $memberCircleIds)" :member-count="$memberCounts[$circle->id] ?? 0" :key="'card-'.$circle->id" />
        @endforeach
    </div>
@endif
