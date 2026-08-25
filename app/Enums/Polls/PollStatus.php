<?php

namespace App\Enums\Polls;

/**
 * A Poll's lifecycle. Records WHY a Poll stopped early, never WHETHER it is
 * open — Scheduled/Open/Closed are derived from opens_at/closes_at, so the
 * status can never contradict the clock. See docs/adr/0001.
 */
enum PollStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Concluded = 'concluded';
    case Cancelled = 'cancelled';
}
