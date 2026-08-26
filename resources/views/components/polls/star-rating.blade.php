@props(['points', 'optionId', 'selected' => null, 'label' => null, 'disabled' => false])
@php
    /** @var \Illuminate\Support\Collection $points  ordered scale points, lowest first */
    /** @var int $optionId          the poll option being scored */
    /** @var int|string|null $selected  the currently chosen point id, if any */
    /** @var string|null $label      accessible name for the group */
    /** @var bool $disabled          render read-only (no hover, no writes) */

    // Stars are positional: the nth star means the nth point. Translate the
    // stored point id into that position so Alpine can reason in 1..N.
    $chosenPosition = null;

    foreach ($points->values() as $index => $point) {
        if ((string) $point->id === (string) $selected) {
            $chosenPosition = $index + 1;
        }
    }
@endphp

{{-- Hover fills left-to-right and previews a score without committing it;
     leaving the row falls back to whatever is actually chosen. The write goes
     straight to the Livewire property, so this stays a drop-in replacement for
     the select it stands in for. --}}
<div x-data="{ hover: null, chosen: @js($chosenPosition) }"
     @unless ($disabled) x-on:mouseleave="hover = null" @endunless
     role="radiogroup"
     @if ($label) aria-label="{{ $label }}" @endif
     {{ $attributes->merge(['class' => 'flex items-center gap-0.5']) }}>

    @foreach ($points->values() as $index => $point)
        @php $position = $index + 1; @endphp
        <button type="button"
                role="radio"
                @disabled($disabled)
                aria-label="{{ $point->label }}"
                :aria-checked="chosen === {{ $position }} ? 'true' : 'false'"
                @unless ($disabled)
                    x-on:mouseenter="hover = {{ $position }}"
                    x-on:focus="hover = {{ $position }}"
                    x-on:blur="hover = null"
                    x-on:click="chosen = {{ $position }}; $wire.set('scores.{{ $optionId }}', {{ $point->id }})"
                @endunless
                class="rounded p-0.5 transition disabled:cursor-default"
                :class="((hover ?? chosen) ?? 0) >= {{ $position }} ? 'text-amber-500' : 'text-border-muted'">
            <x-icons.star class="h-6 w-6" />
        </button>
    @endforeach

    {{-- The label of whatever is currently shown, so the row is readable
         without counting stars, and screen readers get a value not a shape. --}}
    <span class="ml-2 text-xs text-muted" aria-live="polite">
        @foreach ($points->values() as $index => $point)
            <span x-show="((hover ?? chosen) ?? 0) === {{ $index + 1 }}" x-cloak>{{ $point->label }}</span>
        @endforeach
        <span x-show="((hover ?? chosen) ?? 0) === 0" x-cloak>{{ __('polls.respond.rank_none') }}</span>
    </span>
</div>
