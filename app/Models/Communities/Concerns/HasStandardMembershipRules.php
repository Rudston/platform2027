<?php

namespace App\Models\Communities\Concerns;

/**
 * Default HasMembershipRules implementation: at most 2 concurrent memberships
 * of the type, a 3-month hold before a swap, and no internal roles. Community
 * models override individual methods as needed (e.g. OrganisationCommunity's
 * allowedInternalRoles()).
 */
trait HasStandardMembershipRules
{
    public function maxConcurrentMemberships(): int
    {
        return 2;
    }

    public function minMembershipMonthsBeforeSwitch(): int
    {
        return 3;
    }

    /** @return list<string> */
    public function allowedInternalRoles(): array
    {
        return [];
    }
}
