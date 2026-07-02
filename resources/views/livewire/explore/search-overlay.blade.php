@php
    /** @var bool $open */
    /** @var string $query */
    /** @var ?string $selectedType */
@endphp
<div>
    @if ($open)
        <div
            class="fixed inset-0 z-50 bg-black/40 p-4"
            wire:click.self="closeSearch"
            x-data
            x-on:keydown.escape.window="$wire.closeSearch()"
        >
            <div class="mx-auto mt-16 max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3">
                    <span aria-hidden="true">🔍</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="query"
                        placeholder="{{ __('explore.search_placeholder') }}"
                        class="w-full border-0 p-0 text-gray-800 placeholder-gray-400 focus:ring-0"
                        autofocus
                        x-init="$nextTick(() => $el.focus())"
                    >
                    <button type="button" wire:click="closeSearch" class="text-gray-400 transition hover:text-gray-600" aria-label="{{ __('explore.close_search') }}">✕</button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    @forelse ($this->results as $result)
                        <button
                            type="button"
                            wire:click="selectResult({{ $result->id }})"
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-gray-50"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-gray-800">{{ $result->name }}</span>
                                <span class="block truncate text-xs text-gray-400">📍 {{ $result->locatable?->name ?? '—' }}</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                {{ $this->badgeFor($result->circleable_type) }}
                            </span>
                        </button>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-gray-400">
                            @if (strlen($query) < 2)
                                {{ __('explore.search_min_chars') }}
                            @else
                                {{ __('explore.search_no_results', ['query' => $query]) }}
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
