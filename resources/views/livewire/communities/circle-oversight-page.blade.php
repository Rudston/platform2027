@php
    /** @var \App\Models\Circles\Circle $circle */
@endphp
<div class="mx-auto min-h-screen w-4/5 py-10">
    <a href="{{ route('communities.show', $circle) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
        {{ __('stewardship.back') }}
    </a>

    <div class="mt-4 rounded-lg border border-border-muted bg-surface p-8 shadow-sm">
        <div class="flex items-baseline justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-main">{{ __('stewardship.heading') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $circle->name }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('stewardship.link') }}</span>
        </div>

        <div class="mt-6 divide-y divide-border-muted overflow-hidden rounded-lg border border-border-muted">
            @forelse ($this->queues as $row)
                <div @class([
                    'flex items-center justify-between gap-4 p-4',
                    'bg-amber-50' => $row['neglected'],
                ])>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-main">{{ $row['label'] }}</span>
                            @if ($row['neglected'])
                                <span class="rounded-full bg-amber-200 px-2 py-0.5 text-xs font-semibold text-amber-900">{{ __('stewardship.overdue') }}</span>
                            @endif
                        </div>
                        <div class="mt-1 text-sm text-muted">
                            {{ __('stewardship.pending') }}:
                            <span class="font-semibold text-main">{{ $row['count'] }}</span>
                            @if ($row['oldest'])
                                · {{ __('stewardship.oldest') }}:
                                <span @class(['font-medium', 'text-amber-800' => $row['neglected'], 'text-main' => ! $row['neglected']])>{{ $row['oldest']->diffForHumans() }}</span>
                            @else
                                · {{ __('stewardship.none') }}
                            @endif
                        </div>
                    </div>
                    <a href="{{ $row['url'] }}"
                       class="shrink-0 rounded-lg border border-border-muted px-3 py-1.5 text-sm font-medium text-indigo-600 transition hover:opacity-80">
                        {{ __('stewardship.view') }}
                    </a>
                </div>
            @empty
                <p class="p-4 text-sm text-muted">{{ __('stewardship.no_queues') }}</p>
            @endforelse
        </div>
    </div>
</div>
