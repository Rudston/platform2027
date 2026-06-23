<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Locatable;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;

class Event extends Model implements Circleable, Locatable
{
    use HasCircle, HasLocation;

    protected $guarded = [];
}
