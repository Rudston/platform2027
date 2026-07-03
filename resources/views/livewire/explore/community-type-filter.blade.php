@php
    /** @var ?string $active */
    /** @var array $pills */
    /** @var string $action */
@endphp
<div class="flex gap-2 overflow-x-auto py-3">
    @foreach ($pills as $pill)
        @php($isActive = $active === $pill['value'])
        <button
            type="button"
            wire:click="$parent.{{ $action }}(@js($pill['value']))"
            @class([
                'flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition',
                'border-indigo-600 bg-indigo-600 text-white shadow-sm' => $isActive,
                'border-border-muted bg-surface text-muted hover:bg-border-muted' => ! $isActive,
            ])
        >
            <span aria-hidden="true">{{ $pill['icon'] }}</span>
            <span>{{ $pill['label'] }}</span>
        </button>
    @endforeach
</div>
