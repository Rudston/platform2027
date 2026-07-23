<?php

namespace App\Enums\Moderation;

/**
 * The decision an admin recorded on a moderation record. Null (no case) means
 * the record is still pending.
 */
enum ModerationAction: string
{
    case Approved = 'approved';
    case Hidden = 'hidden';
    case Deleted = 'deleted';
    case EditedAndApproved = 'edited_and_approved';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Hidden => 'Hidden',
            self::Deleted => 'Deleted',
            self::EditedAndApproved => 'Edited & Approved',
        };
    }
}
