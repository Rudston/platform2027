@php
    /** @var ?string $selectedType */
    /** @var ?string $selectedCommunityType */
    /** @var ?int $selectedCircleId */
    /** @var string $viewMode */
    /** @var array $breadcrumb */
    /** @var ?string $suggestedCommunityUrl */
@endphp
<div
    class="mx-auto max-w-5xl px-4 py-6"
    x-data
    x-init="window.getUserLocation && window.getUserLocation().then((c) => $wire.setUserLocation(c.latitude, c.longitude)).catch(() => {})"
>
    {{-- ===================================================================== --}}
    {{-- TOP SECTION — two columns:                                            --}}
    {{--   left  = geographic / location explorer                              --}}
    {{--   right = LocationCommunity card for the selected location            --}}
    {{-- Collapses to a single column on mobile (left on top, right below).    --}}
    {{-- ===================================================================== --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- LEFT COLUMN — geographic drill-down --}}
        <div>
            {{-- Header --}}
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Explore Communities</h1>
                <button
                    type="button"
                    wire:click="$dispatch('open-search')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    <span aria-hidden="true">🔍</span> Search
                </button>
            </div>

            {{-- Location filter (All / Locations) --}}
            <div class="mt-2">
                <livewire:explore.community-type-filter group="location" :active="$selectedType" :key="'type-filter-top'" />
            </div>

            {{-- Breadcrumb + view toggle (shared geographic context) --}}
            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                <livewire:explore.breadcrumb :breadcrumb="$breadcrumb" :selected-type="$selectedType" :key="'breadcrumb'" />

                <div class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-0.5 shadow-sm">
                    <button
                        type="button"
                        disabled
                        title="Coming soon"
                        class="inline-flex cursor-not-allowed items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium text-gray-400 opacity-60"
                    >
                        <span aria-hidden="true">🗺</span> Map
                    </button>
                    <button
                        type="button"
                        wire:click="setViewMode('browse')"
                        @class([
                            'inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium transition',
                            'bg-indigo-600 text-white' => $viewMode === 'browse',
                            'text-gray-700 hover:bg-gray-50' => $viewMode !== 'browse',
                        ])
                    >
                        <span aria-hidden="true">☰</span> Browse
                    </button>
                </div>
            </div>

            {{-- Location browser (scrollable content area; header/filter/breadcrumb above stay fixed) --}}
            <div class="mt-4 overflow-y-auto" style="max-height: {{ \App\Livewire\Explore\ExploreCommunities::MAX_HEIGHT_LOCATIONS_COLUMN }}">
                @if ($viewMode === 'browse')
                    @php($current = collect($breadcrumb)->last())
                    @if ($this->communities->isNotEmpty())
                        <x-explore.column-browser
                            :communities="$this->communities"
                            :selected-type="$selectedType"
                            :selected-circle-id="$selectedCircleId"
                            :heading="$current['name'] ?? 'South Africa'"
                        />
                    @elseif ($this->isAtTerminalLevel)
                        {{-- MainPlace is the terminal geographic level — no deeper level to drill into. --}}
                        <div class="rounded-lg border border-gray-100 bg-white px-4 py-6 text-center text-sm text-gray-400">
                            No further sub-areas.
                        </div>
                    @elseif ($this->communitiesCountBelow > 0)
                        <x-explore.empty-state
                            :icon="$this->selectedTypeIcon"
                            :heading="'No '.$this->selectedTypeLabel.' at '.$this->currentLevel.' level yet'"
                            subheading="Be the first to start one."
                            :cta-label="'+ Start a '.$this->selectedTypeSingular"
                            cta-action="startCommunity"
                            :below-count="$this->communitiesCountBelow"
                            :below-label="$this->selectedTypeLabel"
                        />
                    @else
                        <x-explore.empty-state
                            :icon="$this->selectedTypeIcon"
                            :heading="'No '.$this->selectedTypeLabel.' here yet'"
                            subheading="This is a fresh space waiting to grow."
                            cta-label="+ Be the first"
                            cta-action="startCommunity"
                            :below-count="0"
                        />
                    @endif
                @else
                    {{-- Map view (Phase 1: disabled; toggle never activates this branch) --}}
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-gray-400">
                        🗺 Map view — coming soon.
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT COLUMN — LocationCommunity card for the selected location --}}
        {{-- (defaults to the national circle). Bottom-aligned so the card's --}}
        {{-- bottom sits level with the bottom of the left column. --}}
        <div class="flex flex-col">
            {{-- Only shown once geolocation → nearest MainPlace → circle → URL resolves. --}}
            @if ($suggestedCommunityUrl)
                <div class="mb-4 flex justify-end">
                    <a
                        href="{{ $suggestedCommunityUrl }}"
                        wire:navigate
                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-600 bg-white px-3 py-2 text-sm font-medium text-indigo-600 shadow-sm transition hover:bg-indigo-50"
                    >
                        Could this be your home community?
                    </a>
                </div>
            @endif

            @if ($this->rightColumnCircle)
                {{-- Same card component/styling used for other community types. --}}
                <div class="mt-auto">
                    <livewire:explore.community-card :circle="$this->rightColumnCircle" :from="$this->exploreUrl" :key="'location-card-'.$this->rightColumnCircle->id" />
                </div>
            @else
                <div class="mt-auto flex min-h-40 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-400">
                    Select a location to explore its community.
                </div>
            @endif
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- BOTTOM SECTION — community types at the selected location             --}}
    {{-- ===================================================================== --}}
    <div class="mt-10 border-t border-gray-200 pt-8">
        @php($current = collect($breadcrumb)->last())
        <h2 class="text-lg font-semibold tracking-tight text-gray-900">
            Communities in {{ $current['name'] ?? 'South Africa' }}
        </h2>
        <p class="mt-0.5 text-sm text-gray-500">
            Organisations, campaigns, courses, theme communities and events at this location.
        </p>

        {{-- Community-type filter (independent of the location filter above) --}}
        <div class="mt-2">
            <livewire:explore.community-type-filter group="community" :active="$selectedCommunityType" :key="'type-filter-bottom'" />
        </div>

        {{-- Community-type content --}}
        <div class="mt-4">
            @if ($selectedCommunityType === null)
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-gray-400">
                    Pick a community type above to see what's here.
                </div>
            @elseif ($this->typeCommunities->isNotEmpty())
                <x-explore.column-browser
                    :communities="$this->typeCommunities"
                    :selected-type="$selectedCommunityType"
                    :selected-circle-id="$selectedCircleId"
                    :from="$this->exploreUrl"
                />
            @elseif ($this->typeCommunitiesCountBelow > 0)
                <x-explore.empty-state
                    :icon="$this->communityTypeIcon"
                    :heading="'No '.$this->communityTypeLabel.' at '.$this->currentLevel.' level yet'"
                    subheading="Be the first to start one."
                    :cta-label="'+ Start a '.$this->communityTypeSingular"
                    cta-action="startCommunityType"
                    :below-count="$this->typeCommunitiesCountBelow"
                    :below-label="$this->communityTypeLabel"
                />
            @else
                <x-explore.empty-state
                    :icon="$this->communityTypeIcon"
                    :heading="'No '.$this->communityTypeLabel.' here yet'"
                    subheading="This is a fresh space waiting to grow."
                    cta-label="+ Be the first"
                    cta-action="startCommunityType"
                    :below-count="0"
                />
            @endif
        </div>
    </div>

    {{-- Search overlay --}}
    <livewire:explore.search-overlay :selected-type="$selectedType" :from="$this->exploreUrl" :key="'search-overlay'" />
</div>
