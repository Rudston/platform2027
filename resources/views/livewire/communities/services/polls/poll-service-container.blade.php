@php
    /** @var \App\Models\Circles\Circle $circle */
@endphp
<div>
    <h2 class="text-lg font-semibold text-main">{{ __('polls.groups_heading') }}</h2>

    {{-- Stats row --}}
    <div class="mt-4 flex flex-wrap items-center gap-4 rounded-lg border border-border-muted bg-surface p-4 text-sm shadow-sm">
        <div>
            <span class="font-semibold text-main">{{ $this->totalGroups }}</span>
            <span class="text-muted">{{ __('polls.total_groups') }}</span>
        </div>
        <div>
            <span class="font-semibold text-main">{{ $this->totalPolls }}</span>
            <span class="text-muted">{{ __('polls.total_polls') }}</span>
        </div>
        {{-- Derived from the clock, not a stored status — see docs/adr/0001. --}}
        <div>
            <span class="font-semibold text-main">{{ $this->openPolls }}</span>
            <span class="text-muted">{{ __('polls.open_polls') }}</span>
        </div>

        @if ($this->canManage)
            <button type="button"
                    wire:click="$dispatch('openModal', { component: 'communities.services.polls.poll-group-modal', arguments: { circleId: {{ $circle->id }} } })"
                    class="ml-auto rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ __('polls.create_group') }}
            </button>
        @endif
    </div>

    {{-- Search + archive filter --}}
    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('polls.search_placeholder') }}"
               class="flex-1 rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
        <select wire:model.live="statusFilter"
                class="rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
            <option value="all">{{ __('polls.filter.all') }}</option>
            <option value="active">{{ __('polls.filter.active') }}</option>
            <option value="archived">{{ __('polls.filter.archived') }}</option>
        </select>
    </div>

    {{-- Group grid --}}
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->groups as $group)
            <div class="flex flex-col rounded-lg border border-border-muted bg-surface p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ $this->groupUrl($group) }}" wire:navigate
                       class="flex min-w-0 items-center gap-2 font-semibold text-main hover:underline">
                        <span aria-hidden="true">🗳️</span>
                        <span class="truncate">{{ $group->name }}</span>
                    </a>

                    @if ($this->canManage)
                        <div x-data="{ open: false }" class="relative shrink-0">
                            <button type="button" x-on:click="open = !open"
                                    class="rounded px-2 py-0.5 text-muted hover:text-main" aria-label="Actions">⋯</button>
                            <div x-show="open" x-on:click.outside="open = false" x-cloak
                                 class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-border-muted bg-surface py-1 text-sm shadow-lg">
                                <button type="button"
                                        wire:click="$dispatch('openModal', { component: 'communities.services.polls.poll-group-modal', arguments: { circleId: {{ $circle->id }}, groupId: {{ $group->id }} } })"
                                        x-on:click="open = false"
                                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-main hover:bg-border-muted">
                                    <x-icons.edit class="h-4 w-4" />{{ __('polls.actions.edit') }}
                                </button>
                                @if ($group->isArchived())
                                    <button type="button" wire:click="restore({{ $group->id }})" x-on:click="open = false"
                                            class="block w-full px-3 py-1.5 text-left text-main hover:bg-border-muted">{{ __('polls.actions.restore') }}</button>
                                @else
                                    {{-- Archiving files the shelf away; the polls on it stay listed. --}}
                                    <button type="button" wire:click="archive({{ $group->id }})"
                                            wire:confirm="{{ __('polls.archive_confirm') }}" x-on:click="open = false"
                                            class="block w-full px-3 py-1.5 text-left text-amber-700 hover:bg-border-muted">{{ __('polls.actions.archive') }}</button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                @if ($group->description)
                    <p class="mt-2 line-clamp-2 text-sm text-muted">{{ $group->description }}</p>
                @endif

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted">
                    <span>{{ __('polls.polls_count', ['count' => $group->polls_count]) }}</span>
                    @if ($group->isArchived())
                        <span class="rounded-full border border-border-muted px-2 py-0.5">{{ __('polls.archived_badge') }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-lg border border-dashed border-border-muted p-6 text-center text-sm text-muted">
                {{ $this->search !== '' ? __('polls.no_groups_found') : __('polls.no_groups') }}
                @if ($this->search === '' && $this->canManage)
                    <p class="mt-1">{{ __('polls.group_intro') }}</p>
                @endif
            </div>
        @endforelse
    </div>
</div>
