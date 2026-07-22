<?php

namespace App\Enums\Moderation;

/**
 * What put a comment into the moderation queue: the AI checker, or a user's
 * "Flag as offensive" click. Ai and User records for the same comment coexist
 * as separate rows.
 */
enum ModerationFlagSource: string
{
    case Ai = 'ai';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI',
            self::User => 'User',
        };
    }
}
