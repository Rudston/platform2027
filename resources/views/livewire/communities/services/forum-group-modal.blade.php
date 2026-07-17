<div>
    <h2 class="text-lg font-semibold text-main">
        {{ $groupId ? __('forums.modal.edit_title') : __('forums.modal.create_title') }}
    </h2>

    <form wire:submit="save" class="mt-4 space-y-4">
        <div>
            <label for="fg-name" class="block text-sm font-medium text-muted">{{ __('forums.modal.name') }}</label>
            <input id="fg-name" type="text" wire:model="name"
                   class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="fg-desc" class="block text-sm font-medium text-muted">{{ __('forums.modal.description') }}</label>
            <textarea id="fg-desc" rows="3" wire:model="description"
                      class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="fg-visibility" class="block text-sm font-medium text-muted">{{ __('forums.modal.visibility') }}</label>
            <select id="fg-visibility" wire:model="visibility"
                    class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
                <option value="public">{{ __('forums.visibility.public') }}</option>
                <option value="private">{{ __('forums.visibility.private') }}</option>
                <option value="invite-only">{{ __('forums.visibility.invite-only') }}</option>
            </select>
            @error('visibility') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @if ($groupId)
            <div class="border-t border-border-muted pt-3">
                <p class="text-sm font-medium text-muted">{{ __('tags.heading') }}</p>
                <div class="mt-2">
                    <livewire:tags.tag-picker
                        :taggable-type="\App\Models\Forums\ForumGroup::class"
                        :taggable-id="$groupId"
                        :key="'fg-tags-'.$groupId" />
                </div>
            </div>
        @endif

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" wire:click="closeModal"
                    class="rounded-lg border border-border-muted px-4 py-2 text-sm transition hover:opacity-80">
                {{ __('ui.cancel') }}
            </button>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ $groupId ? __('forums.modal.save') : __('forums.create_group') }}
            </button>
        </div>
    </form>
</div>
