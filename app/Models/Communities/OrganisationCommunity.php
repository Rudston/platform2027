<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Communities\HasMembershipRules;
use App\Contracts\Locatable;
use App\Contracts\Circles\HasDefaultServices;
use App\Models\Communities\Concerns\HasStandardMembershipRules;
use App\Models\Organisation;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganisationCommunity extends Model implements Circleable, HasDefaultServices, HasMembershipRules, Locatable
{
    use HasCircle, HasLocation, HasStandardMembershipRules, SoftDeletes;

    protected $table = 'organisation_communities';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function circleName(): string
    {
        return $this->organisation?->name
            ?? $this->name
            ?? 'Unnamed Organisation';
    }

    /** {@inheritDoc} — order here is also the tab order on the Community Page. */
    public function defaultServices(): array
    {
        return ['news', 'events', 'forums', 'media', 'voting'];
    }

    /** Organisation communities distinguish staff/board members from the public. */
    public function allowedInternalRoles(): array
    {
        return ['organisation_member'];
    }
}
