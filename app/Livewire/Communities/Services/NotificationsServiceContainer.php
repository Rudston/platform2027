<?php

namespace App\Livewire\Communities\Services;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Services\Circles\NotificationsService;
use Livewire\Component;

/**
 * Thin UI container for the Notifications service. Real data operations delegate to
 * NotificationsService (resolved via service()), never duplicated here. Placeholder view for now.
 *
 * $membership is the viewer's active membership of this circle (null for a
 * visitor); $isVisitor is the convenience boolean (true when not a member).
 */
class NotificationsServiceContainer extends Component
{
    public Circle $circle;

    public ?CircleMembership $membership = null;

    public bool $isVisitor = false;

    public function mount(Circle $circle, ?CircleMembership $membership = null, bool $isVisitor = false): void
    {
        $this->circle = $circle;
        $this->membership = $membership;
        $this->isVisitor = $isVisitor;
    }

    /** Backend service all data operations delegate to. */
    protected function service(): NotificationsService
    {
        return app(NotificationsService::class);
    }

    public function render()
    {
        return view('livewire.communities.services.notifications-service-container');
    }
}
