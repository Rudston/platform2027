<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRequests extends ListRecords
{
    protected static string $resource = RequestResource::class;

    // No create action: requests are raised by the application, not in admin.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
