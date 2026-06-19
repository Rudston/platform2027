<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
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
}
