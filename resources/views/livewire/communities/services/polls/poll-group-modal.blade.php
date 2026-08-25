<div class="p-10">
    <h2 class="text-lg font-semibold text-main">
        {{ $groupId === null ? __('polls.group.create_title') : __('polls.group.edit_title') }}
    </h2>

    <form wire:submit="save" class="mt-6 space-y-5">
        <div>
            <label for="poll-group-name" class="block text-sm font-medium text-main">{{ __('polls.group.name') }}</label>
            <input id="poll-group-name" type="text" wire:model="name"
                   placeholder="{{ __('polls.group.name_placeholder') }}"
                   class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="poll-group-slug" class="block text-sm font-medium text-main">{{ __('polls.group.slug') }}</label>
            <input id="poll-group-slug" type="text" wire:model="slug"
                   class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
            @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="poll-group-description" class="block text-sm font-medium text-main">{{ __('polls.group.description') }}</label>
            <textarea id="poll-group-description" wire:model="description" rows="3"
                      placeholder="{{ __('polls.group.description_placeholder') }}"
                      class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" x-on:click="$dispatch('closeModal')"
                    class="rounded-lg border border-border-muted px-4 py-2 text-sm text-main hover:bg-border-muted">
                {{ __('ui.cancel') }}
            </button>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ __('polls.group.save') }}
            </button>
        </div>
    </form>
</div>
