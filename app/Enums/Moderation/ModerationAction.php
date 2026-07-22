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

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
