<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Models\Circles\Circle;

class ManageMediaService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'manage_media';
    }

    public function getPermissions(): array
    {
        return [];
    }
}
