@props([
    /** Ancestor circles (root → parent), from Circle::ancestors(). @var iterable */
    'ancestors',
])
@php($chain = collect($ancestors))
{{-- Geographic trail matching the Explore breadcrumb's style (📍 › links).
     Renders nothing for a top-level circle with no ancestors. --}}
@if ($chain->isNotEmpty())
    <nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5 text-xs text-muted']) }} aria-label="Breadcrumb">
        <span aria-hidden="true">📍</span>
        @foreach ($chain as $ancestor)
            <a href="{{ route('communities.show', $ancestor) }}" wire:navigate
               class="text-indigo-600 hover:underline">{{ $ancestor->name }}</a>
            @if (! $loop->last)
                <span aria-hidden="true">›</span>
            @endif
        @endforeach
    </nav>
@endif
