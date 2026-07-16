<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Circles\HasDefaultServices;
use App\Contracts\Communities\HasMembershipRules;
use App\Contracts\Locatable;
use App\Models\Communities\Concerns\HasStandardMembershipRules;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationCommunity extends Model implements Circleable, HasDefaultServices, HasMembershipRules, Locatable
{
    use HasCircle, HasLocation, HasStandardMembershipRules, SoftDeletes;

    protected $guarded = [];

    /** {@inheritDoc} — order here is also the tab order on the Community Page. */
    public function defaultServices(): array
    {
        return ['news', 'events', 'forums', 'media', 'voting'];
    }
}
