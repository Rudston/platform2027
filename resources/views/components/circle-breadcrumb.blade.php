@props([
    /** The circle whose trail is shown. @var \App\Models\Circles\Circle */
    'circle',
    /** Pre-fetched ancestors (root → parent) from Circle::ancestors(), to avoid a re-query. */
    'ancestors' => null,
])
@php($crumbs = \App\Support\Circles\ExplorerBreadcrumb::for($circle, $ancestors))
{{-- The trail this community shows in the Explore breadcrumb (📍 › links):
     every crumb — the places, and for sub-communities the closing type crumb —
     opens the community explorer at that point. The circle's own name is NOT a
     crumb; the surface using this shows it (linked) directly below. --}}
<nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5 text-xs text-muted']) }} aria-label="Breadcrumb">
    <span aria-hidden="true">📍</span>
    @foreach ($crumbs as $crumb)
        <a href="{{ $crumb['url'] }}" wire:navigate
           class="text-indigo-600 hover:underline">{{ $crumb['name'] }}</a>
        @if (! $loop->last)
            <span aria-hidden="true">›</span>
        @endif
    @endforeach
</nav>
