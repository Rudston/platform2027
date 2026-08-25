@php
    /** @var \App\Models\Circles\Circle $circle */
    /** @var \App\Models\Polls\PollGroup $group */
    /** @var string $backUrl */
    $stateStyles = [
        'draft'     => 'border-border-muted text-muted',
        'scheduled' => 'border-border-muted text-muted',
        'open'      => 'border-emerald-600 text-emerald-700',
        'closed'    => 'border-border-muted text-main',
        'concluded' => 'border-border-muted text-main',
        'cancelled' => 'border-red-600 text-red-700',
    ];
@endphp
<div class="mx-auto max-w-4xl px-4 py-8">
    <a href="{{ $backUrl }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
        {{ __('polls.back_to_polls') }}
    </a>

    <div class="mt-3 flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="flex items-center gap-2 text-2xl font-semibold text-main">
                <span aria-hidden="true">🗳️</span>
                <span class="truncate">{{ $group->name }}</span>
                @if ($group->isArchived())
                    <span class="rounded-full border border-border-muted px-2 py-0.5 text-xs font-normal text-muted">{{ __('polls.archived_badge') }}</span>
                @endif
            </h1>
            @if ($group->description)
                <p class="mt-2 text-sm text-muted">{{ $group->description }}</p>
            @endif
        </div>

        @if ($this->canManage)
            <button type="button"
                    wire:click="$dispatch('openModal', { component: 'communities.services.polls.poll-modal', arguments: { groupId: {{ $group->id }} } })"
                    class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ __('polls.create_poll') }}
            </button>
        @endif
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($this->polls as $poll)
            @php
                $state = $poll->stateKey();
                $responded = $this->respondentCountFor($poll);
            @endphp
            <div class="rounded-lg border border-border-muted bg-surface p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ $this->pollUrl($poll) }}" wire:navigate
                           class="font-medium text-main hover:underline">{{ $poll->title }}</a>
                        @if ($poll->description)
                            <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $poll->description }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs {{ $stateStyles[$state] }}">
                        {{ __('polls.state.'.$state) }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                    {{-- Turnout: the denominator is fixed at publish, so it never
                         moves once the poll is live (docs/adr/0002). A draft has
                         no electorate yet, so only the numerator is shown. --}}
                    @if ($poll->isDraft())
                        <span>{{ __('polls.turnout_pending', ['responded' => $responded]) }}</span>
                    @else
                        <span>{{ __('polls.turnout', ['responded' => $responded, 'electorate' => $poll->electorate_count]) }}</span>
                    @endif

                    @if ($state === 'scheduled' && $poll->opens_at)
                        <span>{{ __('polls.state_hint.scheduled', ['date' => $poll->opens_at->diffForHumans()]) }}</span>
                    @elseif ($state === 'open' && $poll->closes_at)
                        <span>{{ __('polls.state_hint.open', ['date' => $poll->closes_at->diffForHumans()]) }}</span>
                    @elseif ($state === 'closed' && $poll->closes_at)
                        <span>{{ __('polls.state_hint.closed', ['date' => $poll->closes_at->diffForHumans()]) }}</span>
                    @elseif ($state === 'cancelled')
                        <span class="text-red-700">{{ __('polls.state_hint.cancelled') }}</span>
                    @elseif ($state === 'concluded')
                        <span>{{ __('polls.state_hint.concluded') }}</span>
                    @elseif ($state === 'draft')
                        <span>{{ __('polls.state_hint.draft') }}</span>
                    @endif

                    @if ($poll->organiser)
                        <span>{{ $poll->organiser->name }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-border-muted p-6 text-center text-sm text-muted">
                {{ __('polls.no_polls') }}
            </div>
        @endforelse
    </div>

    {{-- Modal host: the "+ Create Poll" dispatch above needs somewhere to land
         (the Blade $dispatch pattern, as on the forum group page). --}}
    <livewire:wire-elements-modal />
</div>
