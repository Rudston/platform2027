@php
    /** @var \App\Models\Circles\Circle $circle */
@endphp
{{-- Placeholder: real Voting UI + data ops (via VotingService) come later. --}}
<div class="rounded-lg border border-dashed border-border-muted p-6 text-sm text-muted">
    <p class="font-medium text-main">Voting</p>
    <p class="mt-1">VotingServiceContainer — circle #{{ $circle->id }}</p>
</div>
