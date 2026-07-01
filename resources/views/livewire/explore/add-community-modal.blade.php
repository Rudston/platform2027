@php
    /** @var string $type */
    /** @var string $label */
@endphp
<div class="p-6">
    <div class="flex items-start justify-between gap-4">
        <h2 class="text-xl font-bold text-gray-800">Add {{ $label }}</h2>
        <button type="button" wire:click="closeModal" class="text-gray-400 transition hover:text-gray-600" aria-label="Close">
            ✕
        </button>
    </div>

    <p class="mt-4 text-sm text-gray-600">
        The form for adding {{ $label }} will be added here.
    </p>

    <div class="mt-6 flex justify-end">
        <button
            type="button"
            wire:click="closeModal"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
        >
            Close
        </button>
    </div>
</div>
