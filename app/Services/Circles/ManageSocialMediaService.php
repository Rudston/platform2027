<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Livewire\Communities\Services\ManageSocialMediaServiceContainer;
use App\Models\Circles\Circle;

class ManageSocialMediaService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'social_media';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function containerComponent(): ?string
    {
        return ManageSocialMediaServiceContainer::class;
    }
}
