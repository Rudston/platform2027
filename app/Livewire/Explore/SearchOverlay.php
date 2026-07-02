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

    /** Relative Explore URL to return to, passed through to the community page as ?from=. */
    #[Reactive]
    public ?string $from = null;

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

    public function selectResult(int $circleId)
    {
        // Navigate to the community's full page (the detail modal was retired),
        // carrying ?from= so the page's back link restores this Explore view.
        $this->closeSearch();

        $target = $this->from
            ? route('communities.show', ['circle' => $circleId, 'from' => $this->from])
            : route('communities.show', $circleId);

        return $this->redirect($target, navigate: true);
    }

    public function badgeFor(?string $circleableType): string
    {
        return match ($circleableType) {
            CommunityType::LocationCommunity->value => __('communities.singular.location'),
            CommunityType::Organisation->value      => __('communities.singular.organisation'),
            CommunityType::Campaign->value          => __('communities.singular.campaign'),
            CommunityType::Course->value            => __('communities.singular.course'),
            CommunityType::Event->value             => __('communities.singular.event'),
            CommunityType::ThemeCommunity->value    => __('communities.singular.theme'),
            default                                 => __('communities.singular.default'),
        };
    }

    public function render()
    {
        return view('livewire.explore.search-overlay');
    }
}
