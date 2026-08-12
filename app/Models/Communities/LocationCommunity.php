<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Circles\HasDefaultServices;
use App\Contracts\Communities\HasGeographicMembershipBuckets;
use App\Contracts\Communities\HasMembershipRules;
use App\Contracts\Locatable;
use App\Models\Communities\Concerns\HasStandardMembershipRules;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationCommunity extends Model implements Circleable, HasDefaultServices, HasGeographicMembershipBuckets, HasMembershipRules, Locatable
{
    use HasCircle, HasLocation, HasStandardMembershipRules, SoftDeletes;

    protected $guarded = [];

    /** {@inheritDoc} — order here is also the tab order on the Community Page. */
    public function defaultServices(): array
    {
        return ['news', 'events', 'forums', 'media', 'voting'];
    }

    /**
     * {@inheritDoc}
     *
     * The type-wide TOTAL only. Location communities are capped PER GEOGRAPHIC
     * BUCKET (see HasGeographicMembershipBuckets), and `canUserJoin()` applies
     * those bucket caps instead of this number — it is never the operative
     * limit for a single join.
     */
    public function maxConcurrentMemberships(): int
    {
        return $this->maxConcurrentTerminalMemberships() + $this->maxConcurrentUpperMemberships();
    }

    /** {@inheritDoc} — e.g. 2 main places in SA. */
    public function maxConcurrentTerminalMemberships(): int
    {
        return 2;
    }

    /** {@inheritDoc} — e.g. 2 of municipality / district / province / country in SA. */
    public function maxConcurrentUpperMemberships(): int
    {
        return 2;
    }
}
