@php
    /** @var ?string $selectedType */
    /** @var array $pills */
@endphp
<div class="flex gap-2 overflow-x-auto py-3">
    @foreach ($pills as $pill)
        @php($active = $selectedType === $pill['value'])
        <button
            type="button"
            wire:click="$parent.selectType(@js($pill['value']))"
            @class([
                'flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition',
                'border-indigo-600 bg-indigo-600 text-white shadow-sm' => $active,
                'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => ! $active,
            ])
        >
            <span aria-hidden="true">{{ $pill['icon'] }}</span>
            <span>{{ $pill['label'] }}</span>
        </button>
    @endforeach
</div>
