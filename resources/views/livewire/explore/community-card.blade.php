@php
    /** @var \App\Models\Circles\Circle $circle */
@endphp
<div class="flex flex-col rounded-lg border border-border-muted bg-surface p-4 shadow-sm">
    <div class="flex items-start justify-between">
        <span class="text-2xl" aria-hidden="true">{{ $this->icon() }}</span>
        <span class="shrink-0 rounded-full bg-border-muted px-2 py-0.5 text-xs font-medium text-muted">
            {{ $this->levelBadge() }}
        </span>
    </div>

    <h3 class="mt-2 font-semibold text-main">{{ $circle->name }}</h3>
    <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $circle->description }}</p>

    <div class="mt-4 flex items-center justify-between">
        <span class="text-xs text-muted">{{ __('communities.card.members', ['count' => $memberCount]) }}</span>
        <div class="flex items-center gap-2">
            {{-- Pending badge: only pending circles reach the card (admins/superadmins). --}}
            @if ($circle->status === \App\Enums\CircleStatus::Pending)
                <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    {{ __('communities.status_pending') }}
                </span>
            @else
                {{-- Pending circles show the card + badge but no View link. --}}
                <a
                    href="{{ $from ? route('communities.show', ['circle' => $circle, 'from' => $from]) : route('communities.show', $circle) }}"
                    wire:navigate
                    class="rounded-lg border border-indigo-600 px-3 py-1.5 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50"
                >
                    {{ $isMember ? __('ui.enter') : __('ui.visit') }}
                </a>
            @endif
        </div>
    </div>
</div>
