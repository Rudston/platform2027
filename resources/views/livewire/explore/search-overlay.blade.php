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
            <div class="mx-auto mt-16 max-w-2xl overflow-hidden rounded-xl bg-surface shadow-2xl">
                <div class="flex items-center gap-2 border-b border-border-muted px-4 py-3">
                    <span aria-hidden="true">🔍</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="query"
                        placeholder="{{ __('explore.search_placeholder') }}"
                        class="w-full border-0 bg-transparent p-0 text-main placeholder-muted focus:ring-0"
                        autofocus
                        x-init="$nextTick(() => $el.focus())"
                    >
                    <button type="button" wire:click="closeSearch" class="text-muted transition hover:text-main" aria-label="{{ __('explore.close_search') }}">✕</button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    @forelse ($this->results as $result)
                        <button
                            type="button"
                            wire:click="selectResult({{ $result->id }})"
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-border-muted"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-main">{{ $result->name }}</span>
                                <span class="block truncate text-xs text-muted">📍 {{ $result->locatable?->name ?? '—' }}</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-border-muted px-2 py-0.5 text-xs font-medium text-muted">
                                {{ $this->badgeFor($result->circleable_type) }}
                            </span>
                        </button>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-muted">
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
