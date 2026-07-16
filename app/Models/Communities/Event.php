<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Communities\HasMembershipRules;
use App\Contracts\Locatable;
use App\Models\Communities\Concerns\HasStandardMembershipRules;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model implements Circleable, HasMembershipRules, Locatable
{
    use HasCircle, HasLocation, HasStandardMembershipRules, SoftDeletes;

    protected $guarded = [];
}
