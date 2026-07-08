@php
    // Prop declarations for the IDE (defined by @props below).
    /** @var string $key */
    /** @var string $fallback */
    /** @var bool|string|null $collapsible */
    /** @var bool|string|null $collapsed */
    /** @var string|null $title */
@endphp
@props([
    'key',
    'fallback' => '',
    'collapsible' => null,
    'collapsed' => null,
    'title' => null,
])

@php
    // Cached, locale-aware content (current locale → English → fallback).
    $__content = \App\Models\ContentBlock::get($key, $fallback);

    // Block metadata for escaping choice, collapsible behaviour + inline edit link.
    $__block = \App\Models\ContentBlock::query()->where('key', $key)->first();
    $__isHtml = (bool) ($__block?->is_html ?? false);

    // Resolve collapsible behaviour — explicit inline props override the model.
    $__collapsible = is_null($collapsible)
        ? (bool) ($__block?->collapsible ?? false)
        : filter_var($collapsible, FILTER_VALIDATE_BOOLEAN);

    $__collapsed = is_null($collapsed)
        ? (bool) ($__block?->default_collapsed ?? true)
        : filter_var($collapsed, FILTER_VALIDATE_BOOLEAN);

    // Title resolves to the current locale via the translatable accessor.
    $__title = is_null($title)
        ? (string) ($__block?->title ?? '')
        : (string) $title;

    // Inline edit affordance for platform administrators only.
    $__canEdit = auth()->check() && auth()->user()?->hasAnyRole(['superadmin', 'admin']);
    $__editUrl = ($__block && $__canEdit)
        ? route('filament.admin.resources.content-blocks.edit', ['record' => $__block->getKey()])
        : null;
@endphp

{{-- Render nothing (silently) when there is neither content nor an edit affordance. --}}
@if ($__content !== '' || $__editUrl)
    @if ($__collapsible)
        {{-- Collapsible disclosure: title on the left, +/- toggle on the right.
             Initial state is server-rendered to match Alpine so there is no
             flash before Alpine initialises (the project has no x-cloak CSS). --}}
        <div
            x-data="{ open: @js(! $__collapsed) }"
            {{ $attributes->merge(['class' => 'group relative rounded-lg border border-border-muted bg-surface']) }}
        >
            <button
                type="button"
                x-on:click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-main"
            >
                <span class="font-medium">{{ $__title }}</span>

                {{-- +/- toggle: server renders the correct glyph, Alpine keeps it in sync. --}}
                <span
                    aria-hidden="true"
                    x-text="open ? '−' : '+'"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border-muted text-lg leading-none text-muted"
                >{{ $__collapsed ? '+' : '−' }}</span>
            </button>

            <div
                x-show="open"
                x-collapse
                @if ($__collapsed) style="display: none;" @endif
                class="px-4 pb-4 text-main"
            >
                @if ($__isHtml)
                    {!! $__content !!}
                @else
                    {{ $__content }}
                @endif
            </div>

            @if ($__editUrl)
                <a
                    href="{{ $__editUrl }}"
                    target="_blank"
                    rel="noopener"
                    title="Edit content block: {{ $key }}"
                    aria-label="Edit content block"
                    class="absolute -right-2 -top-2 rounded-full border border-border-muted bg-surface p-1 text-muted opacity-0 shadow-sm transition hover:text-main group-hover:opacity-100"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                    </svg>
                </a>
            @endif
        </div>
    @else
        {{-- Non-collapsible: render content directly, as before (no wrapper change). --}}
        <div {{ $attributes->merge(['class' => 'group relative']) }}>
            @if ($__isHtml)
                {!! $__content !!}
            @else
                {{ $__content }}
            @endif

            @if ($__editUrl)
                <a
                    href="{{ $__editUrl }}"
                    target="_blank"
                    rel="noopener"
                    title="Edit content block: {{ $key }}"
                    aria-label="Edit content block"
                    class="absolute -right-2 -top-2 rounded-full border border-border-muted bg-surface p-1 text-muted opacity-0 shadow-sm transition hover:text-main group-hover:opacity-100"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                    </svg>
                </a>
            @endif
        </div>
    @endif
@endif
