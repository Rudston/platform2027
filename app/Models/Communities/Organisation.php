<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Traits\HasCircle;
use Illuminate\Database\Eloquent\Model;

class Organisation extends Model implements Circleable
{
    use HasCircle;

    protected $guarded = [];
}
