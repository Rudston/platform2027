<div>
    {{-- Current tags --}}
    <div class="flex flex-wrap items-center gap-1.5">
        @forelse ($this->tags as $tag)
            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                {{ $tag->name }}
                @if ($this->canManage)
                    <button type="button" wire:click="detach({{ $tag->id }})"
                            class="text-indigo-400 hover:text-indigo-700" aria-label="{{ __('tags.remove') }}">&times;</button>
                @endif
            </span>
        @empty
            <span class="text-xs text-muted">{{ __('tags.none') }}</span>
        @endforelse
    </div>

    {{-- Attach existing tags (managers only) --}}
    @if ($this->canManage)
        <div class="relative mt-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('tags.search_placeholder') }}"
                   class="w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">

            @if ($this->matches->isNotEmpty())
                <div class="absolute z-10 mt-1 w-full rounded-lg border border-border-muted bg-surface py-1 text-sm shadow-lg">
                    @foreach ($this->matches as $match)
                        <button type="button" wire:click="attach({{ $match->id }})"
                                class="block w-full px-3 py-1.5 text-left text-main hover:bg-border-muted">{{ $match->name }}</button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Suggest a tag (any authenticated user) --}}
    @auth
        <div class="mt-2">
            @if (! $showSuggest)
                <button type="button" wire:click="$set('showSuggest', true)"
                        class="text-xs text-indigo-600 hover:underline">{{ __('tags.suggest_cta') }}</button>
            @else
                <div class="rounded-lg border border-border-muted p-3">
                    <p class="text-sm font-medium text-main">{{ __('tags.suggest_title') }}</p>
                    <form wire:submit="submitSuggestion" class="mt-2 space-y-2">
                        <div>
                            <input type="text" wire:model="suggestName" placeholder="{{ __('tags.suggest_name') }}"
                                   class="w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
                            @error('suggestName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <textarea wire:model="suggestDescription" rows="2" placeholder="{{ __('tags.suggest_description') }}"
                                      class="w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main"></textarea>
                            @error('suggestDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="$set('showSuggest', false)"
                                    class="rounded-lg border border-border-muted px-3 py-1.5 text-xs transition hover:opacity-80">{{ __('ui.cancel') }}</button>
                            <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-700">{{ __('tags.suggest_submit') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @endauth
</div>
