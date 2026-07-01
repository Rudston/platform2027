@php
    /** @var string $parentLocationName */
    /** @var int $parentCircleId */
    /** @var string $locationName */
@endphp
<div class="p-6">
    <div class="flex items-start justify-between gap-4">
        <h2 class="text-xl font-bold text-gray-800">Request a location in {{ $parentLocationName }}</h2>
        <button type="button" wire:click="closeModal" class="text-gray-400 transition hover:text-gray-600" aria-label="Close">
            ✕
        </button>
    </div>

    <p class="mt-2 text-sm text-gray-500">We will let you know once it has been added.</p>

    <div class="mt-5">
        <label for="request-location-name" class="block text-sm font-medium text-gray-700">Location name</label>
        <input
            type="text"
            id="request-location-name"
            wire:model="locationName"
            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        />
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <button
            type="button"
            wire:click="closeModal"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
        >
            Cancel
        </button>
        <button
            type="button"
            wire:click="sendRequest"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
        >
            Send Request
        </button>
    </div>
</div>
