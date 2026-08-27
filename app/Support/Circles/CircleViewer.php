<?php

namespace App\Support\Circles;

use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\User;

/**
 * A viewer's standing in ONE Circle, resolved once and then free to ask.
 *
 * Gates that run over a LIST — every poll on a group page — cannot each look up
 * membership without an N+1, and a gate handed a bare "is a manager" boolean
 * can be lied to by its caller. This resolves both facts FROM the Circle and
 * the User, in one place, so a predicate can take the answer without either
 * problem: the two queries happen once per request, and nothing can claim
 * standing it does not have.
 *
 * It is a snapshot for the duration of a request, not a live view: build it
 * after any membership change you expect it to reflect.
 */
final class CircleViewer
{
    private function __construct(
        public readonly ?User $user,
        public readonly ?CircleMembership $membership,
        /** admin / superadmin / circle_admin of THIS circle. */
        public readonly bool $managesCircle,
    ) {}

    public static function for(Circle $circle, ?User $user): self
    {
        return new self(
            $user,
            $user !== null ? $circle->activeMembership($user) : null,
            $circle->isManageableBy($user),
        );
    }

    /** Someone with no standing at all — a logged-out visitor. */
    public static function visitor(): self
    {
        return new self(null, null, false);
    }

    public function isMember(): bool
    {
        return $this->membership !== null;
    }

    /** Not a member. A manager who never joined is a visitor by this measure. */
    public function isVisitor(): bool
    {
        return $this->membership === null;
    }
}
