<?php

namespace App\Livewire\Communities;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.main')]
class CommunityPage extends Component
{
    public Circle $circle;

    /** Where the "back" link returns to — the Explore view we came from. */
    public string $backUrl;

    public function mount(Circle $circle): void
    {
        // Route-model-bound circle; eager-load the relations the page renders.
        $this->circle = $circle->load(['circleable', 'locatable', 'services']);

        // Restore the exact Explore view we came from (?from=…); fall back to
        // bare /explore. Only accept an internal /explore path (no open redirects).
        $this->backUrl = $this->resolveBackUrl(request()->query('from'));
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
