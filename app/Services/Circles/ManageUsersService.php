<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Models\Circles\Circle;
use App\Services\Circles\Concerns\HasNoContainerComponent;

class ManageUsersService implements CircleServiceContract
{
    use HasNoContainerComponent;

    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'manage_users';
    }

    public function getPermissions(): array
    {
        return [];
    }
}
