<?php

namespace App\Contracts;

use App\Models\Circles\Circle;
use Illuminate\Database\Eloquent\Relations\HasOne;

interface Circleable
{
    public function circle(): HasOne;

    public function hasService(string $serviceKey): bool;

    public function isNestedIn(Circle $circle): bool;
}
