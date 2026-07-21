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
        {{-- For organisation communities the top row splits into two halves:
             left = the existing columns, right = approved organisation members. --}}
        <div @class(['mt-3', 'grid gap-6 lg:grid-cols-2' => (bool) $this->organisation])>
            {{-- LEFT half: location/admins/members + org contact columns --}}
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
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
            </div>{{-- /left half --}}

            {{-- RIGHT half: approved organisation members (org communities only) --}}
            @if ($this->organisation)
                <div>
                    <p class="text-sm font-medium text-main">{{ __('communities.page.organisation_members') }}:</p>
                    <div class="mt-2 max-h-40 overflow-y-auto pr-1 text-sm text-muted">
                        {{ $this->organisationMembers->isNotEmpty()
                            ? $this->organisationMembers->map(fn ($m) => $m->user?->name)->filter()->implode(', ')
                            : __('communities.page.no_organisation_members') }}
                    </div>
                </div>
            @endif
        </div>

        {{-- Description --}}
        @if ($circle->description)
            <p class="mt-4 text-muted">{{ $circle->description }}</p>
        @endif

        {{-- Tags: read-only badge row; managers get an "Edit tags" toggle that
             reveals the attachment picker. --}}
        <div class="mt-4" x-data="{ editingTags: false }">
            @if ($this->tags->isNotEmpty() || $this->canManageTags)
                <div class="flex flex-wrap items-center gap-2">
                    <x-tag-list :tags="$this->tags" />
                    @if ($this->canManageTags)
                        <button type="button" x-on:click="editingTags = !editingTags"
                                class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"><x-icons.edit class="h-3.5 w-3.5" />{{ __('tags.edit') }}</button>
                    @endif
                </div>
            @endif

            @if ($this->canManageTags)
                <div x-show="editingTags" x-cloak class="mt-2">
                    <livewire:tags.tag-picker
                        :taggable-type="\App\Models\Circles\Circle::class"
                        :taggable-id="$circle->id"
                        :key="'circle-tags-'.$circle->id" />
                </div>
            @endif
        </div>

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
                            :membership="$this->membership"
                            :is-visitor="$this->isVisitor"
                            :key="$activeServiceKey"
                        />
                    </div>
                @endif
            </div>
        @endif

        {{-- Membership action — right-aligned at the bottom --}}
        <div class="mt-6 flex flex-col items-end gap-1">
            @guest
                <a href="{{ route('login') }}"
                   class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                    {{ __('communities.page.join') }}
                </a>
            @else
                @if ($this->membership)
                    <div class="flex items-center gap-2">
                        @if ($this->canAddSelfAsCircleAdmin)
                            <button type="button" wire:click="addSelfAsCircleAdmin"
                                    wire:confirm="{{ __('communities.page.add_self_admin_confirm') }}"
                                    class="rounded-lg border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50">
                                {{ __('communities.page.add_self_admin') }}
                            </button>
                        @elseif ($this->isCircleAdminHere)
                            @if ($this->administrators->count() > 1)
                                <button type="button" wire:click="removeSelfAsCircleAdmin"
                                        wire:confirm="{{ __('communities.page.remove_self_admin_confirm') }}"
                                        class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium transition hover:opacity-80">
                                    {{ __('communities.page.remove_self_admin') }}
                                </button>
                            @else
                                {{-- Sole admin: can't drop the role until another is appointed. --}}
                                <button type="button"
                                        x-on:click="alert(@js(__('communities.page.remove_self_admin_sole')))"
                                        class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium transition hover:opacity-80">
                                    {{ __('communities.page.remove_self_admin') }}
                                </button>
                            @endif
                        @endif

                        {{-- Leave is always shown. A circle admin is blocked with a
                             popup (must drop the role first); guarded server-side too. --}}
                        @if ($this->isCircleAdminHere)
                            <button type="button"
                                    x-on:click="alert(@js(__('communities.page.leave_blocked_admin')))"
                                    class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium transition hover:opacity-80">
                                {{ __('communities.page.leave') }}
                            </button>
                        @else
                            <button type="button" wire:click="leave"
                                    wire:confirm="{{ __('communities.page.leave_confirm') }}"
                                    class="rounded-lg border border-border-muted px-4 py-2 text-sm font-medium transition hover:opacity-80">
                                {{ __('communities.page.leave') }}
                            </button>
                        @endif
                    </div>
                @elseif ($this->joinState['allowed'])
                    <button type="button" wire:click="join"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        {{ __('communities.page.join') }}
                    </button>
                @else
                    <button type="button" disabled
                            class="cursor-not-allowed rounded-lg bg-indigo-600/50 px-4 py-2 text-sm font-medium text-white">
                        {{ __('communities.page.join') }}
                    </button>
                    @if ($this->joinState['available_at'])
                        <span class="text-xs text-muted">
                            {{ __('communities.page.join_available_at', ['date' => $this->joinState['available_at']->format('d M Y')]) }}
                        </span>
                    @endif
                @endif
            @endguest
        </div>

        {{-- Join modal: internal-role question and/or swap picker (only shown
             when there's something to ask — see CommunityPage::join()). --}}
        @if ($showJoinModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="join-modal">
                <div class="w-full max-w-md rounded-lg border border-border-muted bg-surface p-6 shadow-lg">
                    <h2 class="text-lg font-semibold text-main">{{ __('communities.page.join_modal_title') }}</h2>

                    @if (in_array('organisation_member', $this->allowedInternalRoles, true))
                        <label class="mt-4 flex items-start gap-2 text-sm text-muted">
                            <input type="checkbox" wire:model="joinAsOrgMember" class="mt-0.5">
                            <span>{{ __('communities.page.org_member_question') }}</span>
                        </label>
                    @endif

                    @php($swappable = $this->joinState['swappable'])
                    @if ($swappable->isNotEmpty())
                        <div class="mt-4">
                            <p class="text-sm text-muted">{{ __('communities.page.swap_prompt') }}</p>
                            <div class="mt-2 space-y-1">
                                @foreach ($swappable as $swap)
                                    <label class="flex items-center gap-2 text-sm text-main">
                                        <input type="radio" wire:model="dropMembershipId" value="{{ $swap->id }}">
                                        <span>{{ $swap->circle->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showJoinModal', false)"
                                class="rounded-lg border border-border-muted px-4 py-2 text-sm transition hover:opacity-80">
                            {{ __('ui.cancel') }}
                        </button>
                        <button type="button" wire:click="completeJoin"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                            {{ __('communities.page.join') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Future: type-specific panels (Organisation / Campaign / Course /
             ThemeCommunity / Event / LocationCommunity) slot in below here. --}}
    </div>

    {{-- Modal host (wire-elements/modal) — used by the Forums create/edit modal. --}}
    <livewire:wire-elements-modal />
</div>
