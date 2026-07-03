@php
    /** @var string $parentLocationName */
    /** @var int $parentCircleId */
    /** @var string $locationName */
@endphp
<div class="p-6">
    <div class="flex items-start justify-between gap-4">
        <h2 class="text-xl font-bold text-main">{{ __('communities.request_modal.title', ['place' => $parentLocationName]) }}</h2>
        <button type="button" wire:click="closeModal" class="text-muted transition hover:text-main" aria-label="{{ __('ui.close') }}">
            ✕
        </button>
    </div>

    <p class="mt-2 text-sm text-muted">{{ __('communities.request_modal.subtitle') }}</p>

    <div class="mt-5">
        <label for="request-location-name" class="block text-sm font-medium text-main">{{ __('communities.request_modal.location_name') }}</label>
        <input
            type="text"
            id="request-location-name"
            wire:model="locationName"
            class="mt-1 block w-full rounded-md border border-border-muted bg-surface px-3 py-2 text-sm text-main shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        />
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <button
            type="button"
            wire:click="closeModal"
            class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium text-muted transition hover:bg-border-muted"
        >
            {{ __('ui.cancel') }}
        </button>
        <button
            type="button"
            wire:click="sendRequest"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
        >
            {{ __('communities.request_modal.send') }}
        </button>
    </div>
</div>
