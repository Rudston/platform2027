@props([
    /** @var \Illuminate\Support\Collection<int, \App\Models\Theme> */
    'tags',
])

@php
    // Alphabetical by name; plain understated bordered pills (no icons/colour).
    $sorted = collect($tags)->sortBy(fn ($t) => mb_strtolower((string) $t->name))->values();
@endphp

@if ($sorted->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
        @foreach ($sorted as $tag)
            <span class="rounded-full border border-border-muted px-2 py-0.5 text-xs text-muted">{{ $tag->name }}</span>
        @endforeach
    </div>
@endif
