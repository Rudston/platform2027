<?php

namespace App\Contracts;

use App\Models\Circles\Circle;

interface CircleServiceContract
{
    public function boot(Circle $circle): void;

    public function getKey(): string;

    public function getPermissions(): array;
}
