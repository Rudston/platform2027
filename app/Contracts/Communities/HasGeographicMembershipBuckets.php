<?php

namespace App\Contracts\Communities;

/**
 * A community type whose concurrent-membership cap is split into two GEOGRAPHIC
 * buckets instead of one type-wide number: the terminal ("lowest") level, and
 * every level above it. Filling up on lowest-level communities therefore leaves
 * a user's allowance for wider areas untouched, and vice versa.
 *
 * Implemented by LocationCommunity. `Circle::canUserJoin()` picks the bucket
 * from the circle's `locatable_type` via `LocatableType::isTerminal()` — the
 * country-agnostic definition of "lowest level" (a new country's bottom tier
 * maps to LocationLevel::Place, so nothing here needs configuring per country).
 *
 * The swap rule (minMembershipMonthsBeforeSwitch) applies WITHIN a bucket: a
 * provincial membership can never be dropped to free a lowest-level slot.
 */
interface HasGeographicMembershipBuckets
{
    /** Max concurrent ACTIVE memberships at the terminal (lowest) geographic level. */
    public function maxConcurrentTerminalMemberships(): int;

    /** Max concurrent ACTIVE memberships ABOVE the terminal level. */
    public function maxConcurrentUpperMemberships(): int;
}
