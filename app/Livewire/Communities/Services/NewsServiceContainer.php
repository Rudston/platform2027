<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Services\Circles\NewsService;
use Livewire\Component;

/**
 * Thin UI container for the News service. Real data operations delegate to
 * NewsService (resolved via service()), never duplicated here. Placeholder
 * view for now — see render().
 */
class NewsServiceContainer extends Component
{
    public Circle $circle;

    public function mount(Circle $circle): void
    {
        $this->circle = $circle;
    }

    /** Backend service all data operations delegate to. */
    protected function service(): NewsService
    {
        return app(NewsService::class);
    }

    public function render()
    {
        return view('livewire.communities.services.news-service-container');
    }
}
