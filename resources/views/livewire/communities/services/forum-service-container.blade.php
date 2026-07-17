@php
    /** @var \App\Models\Circles\Circle $circle */
    use App\Enums\Forums\ForumGroupStatus;
@endphp
<div>
    {{-- Stats row --}}
    <div class="flex flex-wrap items-center gap-4 rounded-lg border border-border-muted bg-surface p-4 text-sm shadow-sm">
        <div>
            <span class="font-semibold text-main">{{ $this->totalGroups }}</span>
            <span class="text-muted">{{ __('forums.total_groups') }}</span>
        </div>
        {{-- Participants: hardcoded 0 — built out later. --}}
        <div>
            <span class="font-semibold text-main">0</span>
            <span class="text-muted">{{ __('forums.participants') }}</span>
        </div>
        <div>
            <span class="font-semibold text-main">{{ $this->totalDiscussions }}</span>
            <span class="text-muted">{{ __('forums.total_discussions') }}</span>
        </div>

        @if ($this->canManage)
            <button type="button"
                    wire:click="$dispatch('openModal', { component: 'communities.services.forum-group-modal', arguments: { circleId: {{ $circle->id }} } })"
                    class="ml-auto rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ __('forums.create_group') }}
            </button>
        @endif
    </div>

    {{-- Search + status filter --}}
    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('forums.search_placeholder') }}"
               class="flex-1 rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
        <select wire:model.live="statusFilter"
                class="rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
            <option value="all">{{ __('forums.filter.all') }}</option>
            <option value="active">{{ __('forums.filter.active') }}</option>
            <option value="deactivated">{{ __('forums.filter.deactivated') }}</option>
            <option value="archived">{{ __('forums.filter.archived') }}</option>
        </select>
    </div>

    {{-- Group grid --}}
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->groups as $group)
            <div class="flex flex-col rounded-lg border border-border-muted bg-surface shadow-sm">
                {{-- Banner (placeholder — not a DB column) --}}
                <div class="flex h-16 items-center justify-center rounded-t-lg bg-border-muted/40 text-xs text-muted">
                    {{ __('forums.banner_placeholder') }}
                </div>

                <div class="flex flex-col gap-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <a href="{{ $this->discussionsUrl($group) }}" wire:navigate
                           class="flex min-w-0 items-center gap-2 font-semibold text-main hover:underline">
                            <span aria-hidden="true">💬</span>
                            <span class="truncate">{{ $group->name }}</span>
                        </a>

                        @if ($this->canManage)
                            <div x-data="{ open: false }" class="relative shrink-0">
                                <button type="button" x-on:click="open = !open"
                                        class="rounded px-2 py-0.5 text-muted hover:text-main" aria-label="Actions">⋯</button>
                                <div x-show="open" x-on:click.outside="open = false" x-cloak
                                     class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-border-muted bg-surface py-1 text-sm shadow-lg">
                                    <button type="button" wire:click="$dispatch('openModal', { component: 'communities.services.forum-group-modal', arguments: { circleId: {{ $circle->id }}, groupId: {{ $group->id }} } })" x-on:click="open = false"
                                            class="block w-full px-3 py-1.5 text-left text-main hover:bg-border-muted">{{ __('forums.actions.edit') }}</button>
                                    <a href="{{ $this->discussionsUrl($group) }}" wire:navigate
                                       class="block px-3 py-1.5 text-main hover:bg-border-muted">{{ __('forums.actions.discussions') }}</a>
                                    <button type="button" wire:click="deactivate({{ $group->id }})"
                                            wire:confirm="{{ __('forums.deactivate_confirm') }}" x-on:click="open = false"
                                            class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-border-muted">{{ __('forums.actions.deactivate') }}</button>
                                </div>
                            </div>
                        @endif
                    </div>

                    <p class="line-clamp-2 text-sm text-muted">{{ $group->description }}</p>

                    {{-- Tags: read-only row; managers get an Edit-tags link that
                         opens the group's edit modal (where the picker lives). --}}
                    @if ($group->tags->isNotEmpty() || $this->canManage)
                        <div class="flex flex-wrap items-center gap-2">
                            <x-tag-list :tags="$group->tags" />
                            @if ($this->canManage)
                                <button type="button" wire:click="$dispatch('openModal', { component: 'communities.services.forum-group-modal', arguments: { circleId: {{ $circle->id }}, groupId: {{ $group->id }} } })"
                                        class="text-xs text-indigo-600 hover:underline">{{ __('tags.edit') }}</button>
                            @endif
                        </div>
                    @endif

                    <div class="text-xs text-muted">
                        {{ __('forums.participants') }} 0 ·
                        {{ __('forums.discussions_count', ['count' => $group->discussions_count]) }} ·
                        {{ __('forums.created', ['date' => $group->created_at->format('d M Y')]) }}
                    </div>

                    <div class="mt-1 flex items-center justify-between">
                        @php($status = $group->status)
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-green-100 text-green-800' => $status === ForumGroupStatus::Active,
                            'bg-border-muted text-muted' => $status === ForumGroupStatus::Deactivated,
                            'bg-amber-100 text-amber-800' => $status === ForumGroupStatus::Archived,
                        ])>{{ __('forums.status.'.$status->value) }}</span>

                        @if ($this->canManage)
                            <button type="button" wire:click="$dispatch('openModal', { component: 'communities.services.forum-group-modal', arguments: { circleId: {{ $circle->id }}, groupId: {{ $group->id }} } })"
                                    class="rounded-lg border border-indigo-600 px-3 py-1 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50">
                                {{ __('forums.actions.manage') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-lg border border-dashed border-border-muted p-8 text-center text-sm text-muted">
                {{ __('forums.no_groups') }}
            </div>
        @endforelse
    </div>
</div>
