<div class="p-10">
    <h2 class="text-lg font-semibold text-main">
        {{ $groupId ? __('forums.modal.edit_title') : __('forums.modal.create_title') }}
    </h2>

    <form wire:submit="save" class="mt-6 space-y-8">
        {{-- Basic Information --}}
        <section class="space-y-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('forums.modal.section_basic') }}</h3>

            <div>
                <label for="fg-name" class="block text-sm font-medium text-muted">{{ __('forums.modal.name') }}</label>
                <input id="fg-name" type="text" wire:model="name" placeholder="{{ __('forums.modal.name_placeholder') }}"
                       class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fg-slug" class="block text-sm font-medium text-muted">{{ __('forums.modal.slug') }}</label>
                <input id="fg-slug" type="text" wire:model="slug" placeholder="{{ __('forums.modal.slug_placeholder') }}"
                       class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
                <p class="mt-1 text-xs text-muted">{{ __('forums.modal.slug_note') }}</p>
                @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fg-desc" class="block text-sm font-medium text-muted">{{ __('forums.modal.description') }}</label>
                <textarea id="fg-desc" rows="3" wire:model="description" placeholder="{{ __('forums.modal.description_placeholder') }}"
                          class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Visibility & Access --}}
        <section class="space-y-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('forums.modal.section_visibility') }}</h3>

            <div class="space-y-2">
                @foreach (['public', 'private', 'internal'] as $option)
                    <label class="flex items-start gap-2 rounded-lg border border-border-muted p-3 text-sm">
                        <input type="radio" wire:model.live="visibility" value="{{ $option }}" class="mt-0.5">
                        <span>
                            <span class="font-medium text-main">{{ __('forums.visibility.'.$option) }}</span>
                            <span class="block text-muted">{{ __('forums.visibility_help.'.$option) }}</span>
                        </span>
                    </label>
                @endforeach
                @error('visibility') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Read-only Group Access block: mirrors the selected visibility's
                 participation floor. Derived display only — never submitted. --}}
            <div class="rounded-lg bg-border-muted/40 p-3 text-sm">
                <span class="font-medium text-main">{{ __('forums.modal.group_access') }}:</span>
                <span class="text-muted">{{ $this->participationNote }}</span>
            </div>
        </section>

        {{-- Tags (edit only — a group must exist to tag). Unchanged HasTags picker. --}}
        @if ($groupId)
            <section class="space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('tags.heading') }}</h3>
                <livewire:tags.tag-picker
                    :taggable-type="\App\Models\Forums\ForumGroup::class"
                    :taggable-id="$groupId"
                    :key="'fg-tags-'.$groupId" />
            </section>
        @endif

        {{-- Group Images (empty for now) --}}
        <section class="space-y-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('forums.modal.section_images') }}</h3>
        </section>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" wire:click="closeModal"
                    class="rounded-lg border border-border-muted px-4 py-2 text-sm transition hover:opacity-80">
                {{ __('ui.cancel') }}
            </button>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ __('forums.modal.save') }}
            </button>
        </div>
    </form>
</div>
