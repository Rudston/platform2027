<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Services\Circles\VotingService;
use Livewire\Component;

/**
 * Thin UI container for the Voting service. Real data operations delegate to
 * VotingService (resolved via service()), never duplicated here. Placeholder
 * view for now — see render().
 */
class VotingServiceContainer extends Component
{
    public Circle $circle;

    public function mount(Circle $circle): void
    {
        $this->circle = $circle;
    }

    /** Backend service all data operations delegate to. */
    protected function service(): VotingService
    {
        return app(VotingService::class);
    }

    public function render()
    {
        return view('livewire.communities.services.voting-service-container');
    }
}
