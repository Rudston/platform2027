<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Services\Circles\ManageLearningService;
use Livewire\Component;

/**
 * Thin UI container for the Learning service. Real data operations delegate to
 * ManageLearningService (resolved via service()), never duplicated here.
 * Placeholder view for now — see render().
 */
class ManageLearningServiceContainer extends Component
{
    public Circle $circle;

    public function mount(Circle $circle): void
    {
        $this->circle = $circle;
    }

    /** Backend service all data operations delegate to. */
    protected function service(): ManageLearningService
    {
        return app(ManageLearningService::class);
    }

    public function render()
    {
        return view('livewire.communities.services.manage-learning-service-container');
    }
}
