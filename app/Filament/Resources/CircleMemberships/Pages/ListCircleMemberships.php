<?php

namespace App\Filament\Resources\CircleMemberships\Pages;

use App\Filament\Resources\CircleMemberships\CircleMembershipResource;
use Filament\Resources\Pages\ListRecords;

class ListCircleMemberships extends ListRecords
{
    protected static string $resource = CircleMembershipResource::class;

    // No create action: memberships are managed through the app's join/leave flow.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
