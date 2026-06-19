<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Models\Circles\Circle;

class StoreAssetsService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'store_assets';
    }

    public function getPermissions(): array
    {
        return [];
    }
}
