<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Livewire\Communities\Services\ForumServiceContainer;
use App\Models\Circles\Circle;

class ForumService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'forums';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function containerComponent(): ?string
    {
        return ForumServiceContainer::class;
    }
}
