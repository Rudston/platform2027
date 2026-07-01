<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Locatable;
use App\Models\Organisation;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganisationCommunity extends Model implements Circleable, Locatable
{
    use HasCircle, HasLocation, SoftDeletes;

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
}
