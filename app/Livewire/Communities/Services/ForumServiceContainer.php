<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Services\Circles\ForumService;
use Livewire\Component;

/**
 * Thin UI container for the Forum service. Real data operations delegate to
 * ForumService (resolved via service()), never duplicated here. Placeholder
 * view for now — see render().
 */
class ForumServiceContainer extends Component
{
    public Circle $circle;

    public function mount(Circle $circle): void
    {
        $this->circle = $circle;
    }

    /** Backend service all data operations delegate to. */
    protected function service(): ForumService
    {
        return app(ForumService::class);
    }

    public function render()
    {
        return view('livewire.communities.services.forum-service-container');
    }
}
