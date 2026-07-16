<?php

namespace App\Contracts\Communities;

/**
 * Membership rules for a community type. Implemented by every CommunityType-
 * mapped model; the HasStandardMembershipRules trait provides sensible defaults.
 */
interface HasMembershipRules
{
    /** Max concurrent ACTIVE memberships a user may hold of this community type. */
    public function maxConcurrentMemberships(): int;

    /** Months a membership must be held before it may be swapped for a new one. */
    public function minMembershipMonthsBeforeSwitch(): int;

    /**
     * Internal roles this community type offers (empty if it has none).
     *
     * @return list<string>
     */
    public function allowedInternalRoles(): array;
}
