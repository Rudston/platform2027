<?php

namespace App\Livewire\Communities;

use App\Contracts\Circles\HasDefaultServices;
use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use App\Models\Circles\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.main')]
class CommunityPage extends Component
{
    public Circle $circle;

    /** Where the "back" link returns to — the Explore view we came from. */
    public string $backUrl;

    /** Key of the currently-selected service tab. TODO: #[Url] sync (stub). */
    public string $activeServiceKey = '';

    public function mount(Circle $circle): void
    {
        // Pending circles are not publicly viewable — only admins/superadmins
        // may reach them by direct URL (mirrors the Explore visibleTo() scope).
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        abort_unless($circle->isVisibleTo($user), 404);

        // Route-model-bound circle; eager-load the relations the page renders.
        $this->circle = $circle->load(['circleable', 'locatable', 'services']);

        // Restore the exact Explore view we came from (?from=…); fall back to
        // bare /explore. Only accept an internal /explore path (no open redirects).
        $this->backUrl = $this->resolveBackUrl(request()->query('from'));

        // First service tab is active by default. TODO: #[Url] sync (stub).
        $this->activeServiceKey = $this->serviceTabs()->first()['key'] ?? '';
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
     * @return Collection<int, \App\Models\User>
     */
    #[Computed]
    public function administrators(): Collection
    {
        return $this->circle->administrators();
    }

    /**
     * Displayed member count. The membership system isn't built yet, so the
     * base is a placeholder 0; a circle_admin counts as a member, so add one
     * when the circle has an administrator.
     */
    #[Computed]
    public function memberCount(): int
    {
        $baseMembers = 0; // TODO: real count once the membership system exists.

        return $baseMembers + ($this->administrators->isNotEmpty() ? 1 : 0);
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

    /** Type icon for the circle's community type (mirrors CommunityCard). */
    public function icon(): string
    {
        return match ($this->circle->circleable_type) {
            CommunityType::LocationCommunity->value => '📍',
            CommunityType::Organisation->value      => '🏛',
            CommunityType::Campaign->value          => '📢',
            CommunityType::Course->value            => '🎓',
            CommunityType::Event->value             => '📅',
            CommunityType::ThemeCommunity->value    => '💡',
            default                                 => '🌍',
        };
    }

    public function joinCommunity(): void
    {
        // Placeholder — the membership system is implemented separately.
        $this->dispatch('join-community', circleId: $this->circle->id);
    }

    public function render()
    {
        return view('livewire.communities.community-page')
            ->title(__('communities.page.title'));
    }
}
