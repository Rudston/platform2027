@php
    /** @var string $type */
    /** @var string $label */
    /** @var string|null $howToKey  Content block key for this type, or null (from the component). */
    $isOrganisation = \App\Enums\CommunityType::tryFrom($type) === \App\Enums\CommunityType::Organisation;
@endphp
<div class="p-6">
    <div class="flex items-start justify-between gap-4">
        <h2 class="text-xl font-bold text-main">{{ __('communities.add_modal.title', ['label' => $label]) }}</h2>
        <button type="button" wire:click="closeModal" class="text-muted transition hover:text-main" aria-label="{{ __('ui.close') }}">
            ✕
        </button>
    </div>

    @guest
        <div class="p-6 text-center space-y-3">
            <p>{{ __('communities.login_to_add') }}</p>
            <a href="{{ route('login') }}" class="text-indigo-600 underline">
                {{ __('ui.login') }}
            </a>
        </div>
    @else
        @if ($isOrganisation)
            {{-- How this works (admin-editable content block) --}}
            <div class="mt-4">
                <x-content-block
                    key="community.how_to_add.organisation"
                    :collapsible="true"
                    :collapsed="true"
                    class="flex-1"
                />
            </div>

            {{-- Duplicate warning (dismissible) --}}
            @if ($duplicateWarning)
                <div class="mt-4 flex items-start justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                    <p>{{ __('communities.organisation_duplicate_warning') }}</p>
                    <button type="button" wire:click="$set('duplicateWarning', false)" class="shrink-0 font-semibold" aria-label="{{ __('ui.close') }}">
                        ✕
                    </button>
                </div>
            @endif

            {{-- Organisation form --}}
            <div class="mt-6 space-y-4">
                <div>
                    <label for="organisationName" class="block text-sm font-medium text-main">
                        {{ __('communities.org_form.organisation_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input id="organisationName" type="text" wire:model="organisationName"
                           class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted" />
                    @error('organisationName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="organisationWebsite" class="block text-sm font-medium text-main">{{ __('communities.org_form.website_url') }}</label>
                    <input id="organisationWebsite" type="url" wire:model="organisationWebsite" placeholder="{{ __('communities.org_form.website_placeholder') }}"
                           class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted" />
                    @error('organisationWebsite') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="organisationDescription" class="block text-sm font-medium text-main">{{ __('communities.org_form.description') }}</label>
                    <textarea id="organisationDescription" rows="3" maxlength="800" wire:model="organisationDescription"
                              class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted"></textarea>
                    @error('organisationDescription') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Contact person divider --}}
                <div class="border-t border-border-muted pt-4">
                    <p class="text-sm font-semibold text-main">{{ __('communities.contact_person_for_approval') }}</p>
                </div>

                <div>
                    <label for="contactName" class="block text-sm font-medium text-main">
                        {{ __('communities.org_form.contact_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input id="contactName" type="text" wire:model="contactName"
                           class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted" />
                    @error('contactName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contactEmail" class="block text-sm font-medium text-main">
                        {{ __('communities.org_form.contact_email') }} <span class="text-red-500">*</span>
                    </label>
                    <input id="contactEmail" type="email" wire:model="contactEmail"
                           class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted" />
                    @error('contactEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contactJobTitle" class="block text-sm font-medium text-main">{{ __('communities.org_form.contact_job_title') }}</label>
                    <input id="contactJobTitle" type="text" wire:model="contactJobTitle"
                           class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted" />
                    @error('contactJobTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end">
                    <button type="button" wire:click="submitOrganisation"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                        {{ __('communities.submit_for_approval') }}
                    </button>
                </div>
            </div>
        @else
            {{-- Existing behaviour for all other community types — unchanged. --}}
            @if ($howToKey)
                <div class="mt-4">
                    <x-content-block :key="$howToKey" :collapsible="true" class="flex-1"/>
                </div>
            @else
                <p class="mt-4 text-sm text-muted">
                    {{ __('communities.add_modal.placeholder', ['label' => $label]) }}
                </p>
            @endif
        @endif
    @endguest

    <div class="mt-6 flex justify-end">
        <button
            type="button"
            wire:click="closeModal"
            class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium text-muted transition hover:bg-border-muted"
        >
            {{ __('ui.close') }}
        </button>
    </div>
</div>
