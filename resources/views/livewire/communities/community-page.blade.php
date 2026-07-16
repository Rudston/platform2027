@php
    /** @var \App\Models\Circles\Circle $circle */
    /** @var string $backUrl */
@endphp
{{-- Full height, 80% width, centred. Rendered in layouts.main (public shell
     with the adaptive top nav). --}}
<div class="mx-auto min-h-screen w-4/5 py-10">
    <a href="{{ $backUrl }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
        {{ __('communities.page.back') }}
    </a>

    <div class="mt-4 rounded-lg border border-border-muted bg-surface p-8 shadow-sm">
        {{-- Header: type icon + name --}}
        <div class="flex items-start gap-3">
            <span class="text-3xl" aria-hidden="true">{{ $this->icon() }}</span>
            <h1 class="text-2xl font-bold text-main">{{ $circle->name }}</h1>
        </div>

        {{-- Top meta: location / admins / members on the left; for organisation
             communities, the organisation's contact details on the right. --}}
        <div class="mt-3 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                {{-- Geographic breadcrumb (temporary: single location line) --}}
                <div class="flex items-center gap-1.5 text-sm text-muted">
                    <span aria-hidden="true">📍</span>
                    <span>{{ $circle->locatable?->name ?? '—' }}</span>
                </div>

                {{-- Circle administrators --}}
                <div class="flex items-center gap-1.5 text-sm text-muted">
                    <span aria-hidden="true">🛡️</span>
                    <span class="font-medium text-main">{{ __('communities.page.admins') }}:</span>
                    <span>
                        {{ $this->administrators->isNotEmpty()
                            ? $this->administrators->pluck('name')->implode(', ')
                            : __('communities.page.no_admins') }}
                    </span>
                </div>

                {{-- Member count (admins count as members) --}}
                <div class="flex items-center gap-1.5 text-sm text-muted">
                    <span aria-hidden="true">👥</span>
                    <span>{{ __('communities.page.members', ['count' => $this->memberCount]) }}</span>
                </div>
            </div>

            {{-- Organisation contact details (organisation communities only) --}}
            @if ($this->organisation)
                @php($org = $this->organisation)
                <div class="space-y-2 text-sm text-muted sm:text-right">
                    <div>
                        <span class="font-medium text-main">{{ __('communities.page.contact') }}:</span>
                        {{ $org->contact_person ?? '—' }}
                    </div>
                    <div>
                        <span class="font-medium text-main">{{ __('communities.page.email') }}:</span>
                        <a href="mailto:{{ $org->contact_email }}" class="text-indigo-600 hover:underline">{{ $org->contact_email }}</a>
                    </div>
                    @if ($org->website)
                        <div>
                            <span class="font-medium text-main">{{ __('communities.page.website') }}:</span>
                            <a href="{{ $org->website }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $org->website }}</a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Description --}}
        @if ($circle->description)
            <p class="mt-4 text-muted">{{ $circle->description }}</p>
        @endif

        {{-- Service tabs (replaces the old service badges). Each attached
             service with a container component renders as a tab; the active
             tab's Livewire container is rendered below. TODO: #[Url] sync for
             the active tab (stub, consistent with the rest of this page). --}}
        @if ($this->serviceTabs->isNotEmpty())
            <div class="mt-6">
                <div class="flex flex-wrap gap-1 border-b border-border-muted">
                    @foreach ($this->serviceTabs as $tab)
                        <button
                            type="button"
                            wire:click="selectService('{{ $tab['key'] }}')"
                            @class([
                                '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                                'border-indigo-600 text-indigo-600' => $activeServiceKey === $tab['key'],
                                'border-transparent text-muted hover:text-main' => $activeServiceKey !== $tab['key'],
                            ])
                        >
                            {{ $tab['name'] }}
                        </button>
                    @endforeach
                </div>

                @if ($this->activeContainer)
                    <div class="mt-4">
                        <livewire:dynamic-component
                            :component="$this->activeContainer"
                            :circle="$circle"
                            :key="$activeServiceKey"
                        />
                    </div>
                @endif
            </div>
        @endif

        {{-- Join (placeholder) — right-aligned at the bottom --}}
        <div class="mt-6 flex justify-end">
            <button
                type="button"
                wire:click="joinCommunity"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                {{ __('communities.page.join') }}
            </button>
        </div>

        {{-- Future: type-specific panels (Organisation / Campaign / Course /
             ThemeCommunity / Event / LocationCommunity) slot in below here. --}}
    </div>
</div>
