@php
    /** @var string $heading */
    /** @var string $body */
@endphp
<div>
    <h1 class="text-2xl font-bold text-main">{{ $heading }}</h1>
    <div class="mt-4 rounded-lg border border-dashed border-border-muted bg-surface p-10 text-center">
        <p class="text-sm text-muted">{{ $body }}</p>
    </div>
</div>
