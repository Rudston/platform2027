<?php

namespace App\Services\Circles;

use App\Models\Circles\Circle;
use App\Models\User;

class CircleMembershipService
{
    public function assignCircleRole(User $user, Circle $circle, string $role): void
    {
        setPermissionsTeamId($circle->id);

        // Remove any existing circle role for this circle first.
        $existingCircleRoles = ['circle_admin', 'circle_full_member', 'circle_visitor'];
        $user->removeRole($existingCircleRoles);

        // Now assign the new one.
        $user->assignRole($role);

        setPermissionsTeamId(null);
    }
}
