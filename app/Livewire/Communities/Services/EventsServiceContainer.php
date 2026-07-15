<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Services\Circles\EventsService;
use Livewire\Component;

/**
 * Thin UI container for the Events service. Real data operations delegate to
 * EventsService (resolved via service()), never duplicated here. Placeholder
 * view for now — see render().
 */
class EventsServiceContainer extends Component
{
    public Circle $circle;

    public function mount(Circle $circle): void
    {
        $this->circle = $circle;
    }

    /** Backend service all data operations delegate to. */
    protected function service(): EventsService
    {
        return app(EventsService::class);
    }

    public function render()
    {
        return view('livewire.communities.services.events-service-container');
    }
}
