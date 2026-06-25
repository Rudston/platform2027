@php
    /** @var ?string $selectedType */
    /** @var ?int $selectedCircleId */
    /** @var string $viewMode */
    /** @var array $breadcrumb */
@endphp
<div class="mx-auto max-w-5xl px-4 py-6">
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

    {{-- Type filter --}}
    <div class="mt-2">
        <livewire:explore.community-type-filter :selected-type="$selectedType" :key="'type-filter'" />
    </div>

    {{-- Breadcrumb + view toggle --}}
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

    {{-- Main content --}}
    <div class="mt-4">
        @if ($viewMode === 'browse')
            @php($current = collect($breadcrumb)->last())
            @if ($this->communities->isNotEmpty())
                <x-explore.column-browser
                    :communities="$this->communities"
                    :selected-type="$selectedType"
                    :selected-circle-id="$selectedCircleId"
                    :heading="$current['name'] ?? 'South Africa'"
                />
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

    {{-- Search overlay --}}
    <livewire:explore.search-overlay :selected-type="$selectedType" :key="'search-overlay'" />

    {{-- Modal host (wire-elements/modal) --}}
    <livewire:wire-elements-modal />
</div>
