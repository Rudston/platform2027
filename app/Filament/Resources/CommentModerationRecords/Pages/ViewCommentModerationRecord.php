<?php

namespace App\Filament\Resources\CommentModerationRecords\Pages;

use App\Filament\Resources\CommentModerationRecords\CommentModerationRecordResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommentModerationRecord extends ViewRecord
{
    protected static string $resource = CommentModerationRecordResource::class;

    /**
     * The same Approve / Edit & Approve / Hide / Delete actions the LIST page
     * offers — so an admin arriving here via the front-end "Pending Review" badge
     * can actually act, not just look. Shared handlers (public static on the
     * resource), not duplicated logic; each is visible only while pending.
     */
    protected function getHeaderActions(): array
    {
        return [
            CommentModerationRecordResource::approveAction(),
            CommentModerationRecordResource::editAndApproveAction(),
            CommentModerationRecordResource::hideAction(),
            CommentModerationRecordResource::deleteRecordAction(),
        ];
    }
}
