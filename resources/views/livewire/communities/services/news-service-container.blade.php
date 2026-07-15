@php
    /** @var \App\Models\Circles\Circle $circle */
@endphp
{{-- Placeholder: real News UI + data ops (via NewsService) come later. --}}
<div class="rounded-lg border border-dashed border-border-muted p-6 text-sm text-muted">
    <p class="font-medium text-main">News</p>
    <p class="mt-1">NewsServiceContainer — circle #{{ $circle->id }}</p>
</div>
