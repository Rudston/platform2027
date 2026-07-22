<?php

namespace App\Filament\Resources\CommentModerationRecords\Pages;

use App\Filament\Resources\CommentModerationRecords\CommentModerationRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListCommentModerationRecords extends ListRecords
{
    protected static string $resource = CommentModerationRecordResource::class;

    // No create action: records are raised by the AI checker and user flags.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
