<?php

namespace App\Filament\Resources\ThemeSuggestions\Pages;

use App\Filament\Resources\ThemeSuggestions\ThemeSuggestionResource;
use Filament\Resources\Pages\ListRecords;

class ListThemeSuggestions extends ListRecords
{
    protected static string $resource = ThemeSuggestionResource::class;

    // No create action: suggestions are raised by users in the app.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
