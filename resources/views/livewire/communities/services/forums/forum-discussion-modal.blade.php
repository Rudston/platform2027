<div class="p-10">
    <h2 class="text-lg font-semibold text-main">{{ __('forums.modal.discussion_title') }}</h2>

    <form wire:submit="save" class="mt-6 space-y-4">
        <div>
            <label for="fd-title" class="block text-sm font-medium text-muted">{{ __('forums.modal.title_label') }}</label>
            <input id="fd-title" type="text" wire:model="title" placeholder="{{ __('forums.modal.title_placeholder') }}"
                   class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="fd-slug" class="block text-sm font-medium text-muted">{{ __('forums.modal.slug') }}</label>
            <input id="fd-slug" type="text" wire:model="slug" placeholder="{{ __('forums.modal.slug_placeholder') }}"
                   class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
            <p class="mt-1 text-xs text-muted">{{ __('forums.modal.discussion_slug_note') }}</p>
            @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="fd-content" class="block text-sm font-medium text-muted">{{ __('forums.modal.content_label') }}</label>
            <textarea id="fd-content" rows="6" wire:model="content" placeholder="{{ __('forums.modal.content_placeholder') }}"
                      class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted"></textarea>
            @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" wire:click="closeModal"
                    class="rounded-lg border border-border-muted px-4 py-2 text-sm transition hover:opacity-80">
                {{ __('ui.cancel') }}
            </button>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ __('forums.modal.save_discussion') }}
            </button>
        </div>
    </form>
</div>
