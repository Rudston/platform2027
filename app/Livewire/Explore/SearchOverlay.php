<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class SearchOverlay extends Component
{
    /** Active type filter from the parent (FQCN) or null. */
    #[Reactive]
    public ?string $selectedType = null;

    public string $query = '';

    public bool $open = false;

    #[On('open-search')]
    public function openSearch(): void
    {
        $this->open = true;
    }

    public function closeSearch(): void
    {
        $this->open = false;
        $this->query = '';
    }

    #[Computed]
    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        return Circle::query()
            ->where('name', 'like', '%'.$this->query.'%')
            ->when($this->selectedType, fn ($q) => $q->where('circleable_type', $this->selectedType))
            ->with(['circleable', 'locatable'])
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    public function selectResult(int $circleId): void
    {
        // Move the browser to the result's location, then open its detail modal.
        $this->dispatch('navigate-to-circle', circleId: $circleId)->to(ExploreCommunities::class);
        $this->dispatch('openModal', component: 'explore.community-detail', arguments: ['circleId' => $circleId]);
        $this->closeSearch();
    }

    public function badgeFor(?string $circleableType): string
    {
        return match ($circleableType) {
            CommunityType::LocationCommunity->value => 'Location',
            CommunityType::Organisation->value      => 'Organisation',
            CommunityType::Campaign->value          => 'Campaign',
            CommunityType::Course->value            => 'Course',
            CommunityType::Event->value             => 'Event',
            CommunityType::ThemeCommunity->value    => 'Theme',
            default                                 => 'Community',
        };
    }

    public function render()
    {
        return view('livewire.explore.search-overlay');
    }
}
