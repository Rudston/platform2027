<?php

namespace App\Livewire\Explore;

use App\Models\Circles\Circle;
use LivewireUI\Modal\ModalComponent;

class CommunityDetail extends ModalComponent
{
    public Circle $circle;

    public function mount(int $circleId): void
    {
        $this->circle = Circle::with(['circleable', 'locatable', 'services'])->findOrFail($circleId);
    }

    public function joinCommunity(): void
    {
        // Placeholder — the membership system is implemented separately.
        $this->dispatch('join-community', circleId: $this->circle->id);
    }

    public function render()
    {
        return view('livewire.explore.community-detail');
    }
}
