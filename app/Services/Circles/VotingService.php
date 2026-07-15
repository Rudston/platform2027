<?php

namespace App\Services\Circles;

use App\Contracts\CircleServiceContract;
use App\Livewire\Communities\Services\VotingServiceContainer;
use App\Models\Circles\Circle;

class VotingService implements CircleServiceContract
{
    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'voting';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function containerComponent(): ?string
    {
        return VotingServiceContainer::class;
    }
}
