@php
    /** @var string $type */
    /** @var string $label */
    /** @var string|null $howToKey  Content block key for this type, or null (from the component). */
@endphp
<div class="p-6">
    <div class="flex items-start justify-between gap-4">
        <h2 class="text-xl font-bold text-main">{{ __('communities.add_modal.title', ['label' => $label]) }}</h2>
        <button type="button" wire:click="closeModal" class="text-muted transition hover:text-main" aria-label="{{ __('ui.close') }}">
            ✕
        </button>
    </div>

    @if ($howToKey)
        <div class="mt-4">
            <x-content-block :key="$howToKey" :collapsible="true" class="flex-1"/>
        </div>
    @else
        <p class="mt-4 text-sm text-muted">
            {{ __('communities.add_modal.placeholder', ['label' => $label]) }}
        </p>
    @endif

    <div class="mt-6 flex justify-end">
        <button
            type="button"
            wire:click="closeModal"
            class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium text-muted transition hover:bg-border-muted"
        >
            {{ __('ui.close') }}
        </button>
    </div>
</div>
