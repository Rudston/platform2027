<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface Locatable
{
    public function location(): MorphTo;

    public function locatedIn(Model $place): bool;

    public function setLocation(Model $place): void;
}
