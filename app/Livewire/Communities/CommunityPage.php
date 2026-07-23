<?php

namespace App\Livewire\Communities;

use App\Contracts\Circles\HasDefaultServices;
use App\Contracts\Communities\HasMembershipRules;
use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Circles\CircleVisit;
use App\Models\Circles\Service;
use App\Models\Communities\OrganisationCommunity;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.main')]
class CommunityPage extends Component
{
    public Circle $circle;

    /** Where the "back" link returns to — the Explore view we came from. */
    public string $backUrl;

    /**
     * Key of the currently-selected service tab, synced to ?service= so deep
     * links / back-links can preselect a tab (e.g. returning from a forum
     * group's Discussions page selects the Forums tab).
     */
    #[Url(as: 'service')]
    public string $activeServiceKey = '';

    /** Join-flow modal state. */
    public bool $showJoinModal = false;

    /** "Are you staff/board?" answer for organisation communities. */
    public bool $joinAsOrgMember = false;

    /** Which existing membership to drop when joining requires a swap. */
    public ?int $dropMembershipId = null;

    public function mount(Circle $circle): void
    {
        // Pending circles are not publicly viewable — only admins/superadmins
        // may reach them by direct URL (mirrors the Explore visibleTo() scope).
        /** @var User|null $user */
        $user = auth()->user();
        abort_unless($circle->isVisibleTo($user), 404);

        // Route-model-bound circle; eager-load the relations the page renders.
        $this->circle = $circle->load(['circleable', 'locatable', 'services']);

        // Record the visit for the dashboard's "Recently Visited" (authed only).
        if ($user !== null) {
            CircleVisit::record($user, $this->circle);
        }

        // Restore the exact Explore view we came from (?from=…); fall back to
        // bare /explore. Only accept an internal /explore path (no open redirects).
        $this->backUrl = $this->resolveBackUrl(request()->query('from'));

        // Honour a valid ?service= from the URL (deep link / back-link);
        // otherwise fall back to the first tab.
        $tabs = $this->serviceTabs();
        if ($this->activeServiceKey === '' || ! $tabs->contains('key', $this->activeServiceKey)) {
            $this->activeServiceKey = $tabs->first()['key'] ?? '';
        }
    }

    private function resolveBackUrl(mixed $from): string
    {
        if (is_string($from) && str_starts_with($from, '/explore')) {
            return $from;
        }

        return route('explore');
    }

    /**
     * Users who administer this circle (circle_admin role scoped to it).
     * Computed so the query runs once per render.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function administrators(): Collection
    {
        return $this->circle->administrators();
    }

    /**
     * Whether the viewer may open this circle's Oversight page — platform
     * admins/superadmins ONLY (deliberately NOT circle_admins; the page watches
     * them). Gates the admin-only oversight link in the header.
     */
    #[Computed]
    public function canOverseeCircle(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'superadmin']);
    }

    /** Circle tags (alphabetical) for the read-only display row. */
    #[Computed]
    public function tags(): Collection
    {
        return $this->circle->tags()->orderBy('name')->get();
    }

    /** Whether the viewer may edit this circle's tags (mirrors manage rights). */
    #[Computed]
    public function canManageTags(): bool
    {
        return $this->circle->canBeTaggedBy(auth()->user());
    }

    /** The viewer's active membership of this circle (null for guests/non-members). */
    #[Computed]
    public function membership(): ?CircleMembership
    {
        $user = auth()->user();

        return $user ? $this->circle->activeMembership($user) : null;
    }

    /** True when the viewer is not an active member (guest or non-member). */
    #[Computed]
    public function isVisitor(): bool
    {
        return $this->membership === null;
    }

    /**
     * Eligibility for joining (for a logged-in non-member). Guests get a
     * not-allowed 'guest' result so the UI prompts a login instead.
     *
     * @return array{allowed: bool, reason: ?string, available_at: ?Carbon, swappable: SupportCollection}
     */
    #[Computed]
    public function joinState(): array
    {
        $user = auth()->user();

        if (! $user) {
            return ['allowed' => false, 'reason' => 'guest', 'available_at' => null, 'swappable' => collect()];
        }

        return $this->circle->canUserJoin($user);
    }

    /**
     * Internal roles this community type offers (empty = no role question).
     *
     * @return list<string>
     */
    #[Computed]
    public function allowedInternalRoles(): array
    {
        $owner = $this->circle->circleable;

        return $owner instanceof HasMembershipRules ? $owner->allowedInternalRoles() : [];
    }

    /**
     * Displayed member count — active rows in circle_memberships. Admins are
     * themselves members now (granted at approval / via the backfill command),
     * so they're already included; no separate admin adjustment needed.
     */
    #[Computed]
    public function memberCount(): int
    {
        return $this->circle->memberships()->whereNull('left_at')->count();
    }

    /**
     * Attached services that have a UI container, as tabs. Ordered by the
     * circleable's declared defaultServices() when it opts in, else by
     * attachment order. Each entry: ['key','name','component'].
     *
     * @return SupportCollection<int, array{key:string, name:string, component:string}>
     */
    #[Computed]
    public function serviceTabs(): SupportCollection
    {
        $services = $this->circle->services
            ->where('pivot.is_active', true)
            ->filter(fn (Service $s): bool => filled($s->container_component));

        $owner = $this->circle->circleable;

        if ($owner instanceof HasDefaultServices) {
            $order = array_flip($owner->defaultServices());
            $services = $services->sortBy(fn (Service $s): int => $order[$s->key] ?? PHP_INT_MAX);
        }

        return $services->map(fn (Service $s): array => [
            'key' => $s->key,
            'name' => $s->name,
            'component' => $s->container_component,
        ])->values();
    }

    /** FQCN of the Livewire container for the active tab, or null. */
    #[Computed]
    public function activeContainer(): ?string
    {
        return $this->serviceTabs()->firstWhere('key', $this->activeServiceKey)['component'] ?? null;
    }

    /** Switch the active service tab (guarded to a real tab). */
    public function selectService(string $key): void
    {
        if ($this->serviceTabs()->contains('key', $key)) {
            $this->activeServiceKey = $key;
        }
    }

    /**
     * The linked Organisation entity when this circle is an organisation
     * community, otherwise null (drives the contact panel on the page).
     */
    #[Computed]
    public function organisation(): ?Organisation
    {
        $circleable = $this->circle->circleable;

        return $circleable instanceof OrganisationCommunity
            ? $circleable->organisation
            : null;
    }

    /**
     * Active members of this circle whose 'organisation_member' internal role
     * has been APPROVED (uses hasApprovedInternalRole — never internal_role
     * alone). Only meaningful for organisation communities.
     *
     * @return Collection<int, CircleMembership>
     */
    #[Computed]
    public function organisationMembers(): Collection
    {
        return $this->circle->memberships()
            ->whereNull('left_at')
            ->where('internal_role', 'organisation_member')
            ->with('user')
            ->get()
            ->filter->hasApprovedInternalRole()
            ->sortBy(fn ($m) => mb_strtolower((string) $m->user?->name))
            ->values();
    }

    /** Type icon for the circle's community type (mirrors CommunityCard). */
    public function icon(): string
    {
        return match ($this->circle->circleable_type) {
            CommunityType::LocationCommunity->value => '📍',
            CommunityType::Organisation->value => '🏛',
            CommunityType::Campaign->value => '📢',
            CommunityType::Course->value => '🎓',
            CommunityType::Event->value => '📅',
            CommunityType::ThemeCommunity->value => '💡',
            default => '🌍',
        };
    }

    /**
     * Begin joining. Opens the modal only when there's something to ask (an
     * internal-role question) or a swap to choose; otherwise joins immediately.
     */
    public function join(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->redirect(route('login'));

            return;
        }

        if ($this->membership) {
            return; // already a member
        }

        $state = $this->joinState;

        if (! $state['allowed']) {
            return; // button is disabled in this state anyway; guard server-side
        }

        $needsRoleQuestion = $this->allowedInternalRoles !== [];
        $needsSwap = $state['swappable']->isNotEmpty();

        if (! $needsRoleQuestion && ! $needsSwap) {
            $this->completeJoin(); // nothing to ask — join straight away

            return;
        }

        // Preselect the only swap option, if there's exactly one.
        $this->dropMembershipId = $needsSwap && $state['swappable']->count() === 1
            ? $state['swappable']->first()->id
            : null;
        $this->joinAsOrgMember = false;
        $this->showJoinModal = true;
    }

    /** Finalise the join (from the modal, or directly for a no-question join). */
    public function completeJoin(): void
    {
        $user = auth()->user();

        if (! $user || $this->membership) {
            $this->showJoinModal = false;

            return;
        }

        // Re-check eligibility server-side (never trust modal state).
        $state = $this->joinState;

        if (! $state['allowed']) {
            $this->showJoinModal = false;

            return;
        }

        $drop = null;

        if ($state['swappable']->isNotEmpty()) {
            $dropId = $this->dropMembershipId ?? $state['swappable']->first()->id;
            $drop = $state['swappable']->firstWhere('id', $dropId);

            if (! $drop) {
                return; // invalid selection — keep the modal open
            }
        }

        $role = ($this->joinAsOrgMember && in_array('organisation_member', $this->allowedInternalRoles, true))
            ? 'organisation_member'
            : null;

        $this->circle->joinAsMember($user, internalRole: $role, dropMembership: $drop);

        $this->showJoinModal = false;
        $this->reset(['joinAsOrgMember', 'dropMembershipId']);
        unset($this->membership, $this->isVisitor, $this->joinState);
    }

    /** Leave the community (voluntary — no time/count restriction). */
    public function leave(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        // A circle_admin must drop the admin role before leaving — otherwise the
        // membership goes but the role lingers. Guarded here too (not just the UI).
        if ($this->circle->isAdministeredBy($user)) {
            return;
        }

        $this->circle->leave($user);
        unset($this->membership, $this->isVisitor, $this->joinState);
    }

    /**
     * A global admin/superadmin who has JOINED this circle may add themselves
     * as a circle_admin (unless they already are one). Members-only so it never
     * appears for a not-joined admin browsing a community.
     */
    #[Computed]
    public function canAddSelfAsCircleAdmin(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        // Self-contained (uses activeMembership, not the membership computed) so
        // the gate is easy to exercise directly.
        return $user !== null
            && $this->circle->activeMembership($user) !== null
            && $user->hasAnyRole(['admin', 'superadmin'])
            && ! $this->circle->isAdministeredBy($user);
    }

    public function addSelfAsCircleAdmin(): void
    {
        // Re-check server-side (never trust the rendered button state).
        if (! $this->canAddSelfAsCircleAdmin()) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();
        $this->circle->addAdministrator($user);

        unset($this->administrators, $this->canAddSelfAsCircleAdmin, $this->isCircleAdminHere);
    }

    /** Whether the viewer currently holds circle_admin on THIS circle. */
    #[Computed]
    public function isCircleAdminHere(): bool
    {
        return $this->circle->isAdministeredBy(auth()->user());
    }

    /**
     * Drop the viewer's own circle_admin role — but only if another admin
     * remains (a circle must never be left adminless via self-removal). The
     * "appoint a new admin first" case is surfaced in the UI; this also guards
     * server-side.
     */
    public function removeSelfAsCircleAdmin(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null || ! $this->circle->isAdministeredBy($user)) {
            return;
        }

        // Never remove the last admin.
        if ($this->circle->administrators()->count() <= 1) {
            return;
        }

        $this->circle->removeAdministrator($user);

        unset($this->administrators, $this->canAddSelfAsCircleAdmin, $this->isCircleAdminHere);
    }

    public function render()
    {
        return view('livewire.communities.community-page')
            ->title(__('communities.page.title'));
    }
}
