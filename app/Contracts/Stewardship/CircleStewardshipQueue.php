<?php

namespace App\Contracts\Stewardship;

use App\Models\Circles\Circle;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A queue of items awaiting a steward's (circle_admin's) attention for a given
 * circle — e.g. pending Requests, or unresolved comment-moderation records. The
 * per-circle Oversight page (admin/superadmin only) iterates the registry in
 * config/stewardship.php and renders one row per implementing class, so adding
 * a new queue is a one-line config change.
 */
interface CircleStewardshipQueue
{
    /** Human label for this queue (e.g. "Pending Requests"). */
    public static function queueLabel(): string;

    /**
     * How many items for this circle are still awaiting action. $viewer is the
     * user the oversight page is being rendered for; a queue may narrow what it
     * reports per viewer (e.g. comment moderation hides Internal-forum records
     * from a plain platform admin). Queues with no visibility concept ignore it.
     */
    public static function pendingCountForCircle(Circle $circle, User $viewer): int;

    /**
     * The created_at of the OLDEST still-pending item for this circle (the
     * oversight page derives the age from it), or null if none are pending.
     * $viewer scopes the result the same way as pendingCountForCircle().
     */
    public static function oldestPendingAgeForCircle(Circle $circle, User $viewer): ?Carbon;

    /** The Filament index URL for this queue, pre-filtered to the circle where supported. */
    public static function filamentUrlForCircle(Circle $circle): string;
}
