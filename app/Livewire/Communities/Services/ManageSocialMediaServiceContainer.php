<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Services\Circles\ManageSocialMediaService;
use Livewire\Component;

/**
 * Thin UI container for the Social Media service. Real data operations delegate
 * to ManageSocialMediaService (resolved via service()), never duplicated here.
 * Placeholder view for now — see render().
 */
class ManageSocialMediaServiceContainer extends Component
{
    public Circle $circle;

    public function mount(Circle $circle): void
    {
        $this->circle = $circle;
    }

    /** Backend service all data operations delegate to. */
    protected function service(): ManageSocialMediaService
    {
        return app(ManageSocialMediaService::class);
    }

    public function render()
    {
        return view('livewire.communities.services.manage-social-media-service-container');
    }
}
