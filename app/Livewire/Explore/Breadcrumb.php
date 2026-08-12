<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class Breadcrumb extends Component
{
    /** @var array<int, array{id: ?int, name: string}> */
    #[Reactive]
    public array $breadcrumb = [];

    #[Reactive]
    public ?string $selectedType = null;

    /**
     * Plural type label appended after the location trail, or null for
     * "All"/Locations (where the locations themselves are the crumbs).
     */
    public function typeLabel(): ?string
    {
        if ($this->selectedType === null || $this->selectedType === CommunityType::LocationCommunity->value) {
            return null;
        }

        return CommunityType::tryFrom($this->selectedType)?->pluralLabel();
    }

    public function render()
    {
        return view('livewire.explore.breadcrumb', [
            'typeLabel' => $this->typeLabel(),
        ]);
    }
}
