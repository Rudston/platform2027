<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Models\Circles\Circle;

class ManageLearningService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'manage_learning';
    }

    public function getPermissions(): array
    {
        return [];
    }
}
