<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Livewire\Communities\Services\ManageLearningServiceContainer;
use App\Models\Circles\Circle;

class ManageLearningService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'learning';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function containerComponent(): ?string
    {
        return ManageLearningServiceContainer::class;
    }
}
