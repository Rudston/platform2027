<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Livewire\Communities\Services\NotificationsServiceContainer;
use App\Models\Circles\Circle;

class NotificationsService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'notifications';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function containerComponent(): ?string
    {
        return NotificationsServiceContainer::class;
    }
}
