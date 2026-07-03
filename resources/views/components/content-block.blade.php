@props([
    'key',
    'fallback' => '',
])

@php
    // Cached, locale-aware content (current locale → English → fallback).
    $__content = \App\Models\ContentBlock::get($key, $fallback);

    // Block metadata for escaping choice + inline edit link.
    $__block = \App\Models\ContentBlock::query()->where('key', $key)->first();
    $__isHtml = (bool) ($__block?->is_html ?? false);

    // Inline edit affordance for platform administrators only.
    $__canEdit = auth()->check() && auth()->user()?->hasAnyRole(['superadmin', 'admin']);
    $__editUrl = ($__block && $__canEdit)
        ? route('filament.admin.resources.content-blocks.edit', ['record' => $__block->getKey()])
        : null;
@endphp

{{-- Render nothing (silently) when there is neither content nor an edit affordance. --}}
@if ($__content !== '' || $__editUrl)
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
