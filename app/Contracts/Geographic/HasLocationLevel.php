<?php

namespace App\Contracts\Geographic;

use App\Enums\LocationLevel;

/**
 * Implemented by every geographic (locatable) model so the platform can work
 * against generic levels rather than country-specific types.
 */
interface HasLocationLevel
{
    /** The generic level this model sits at. */
    public function locationLevel(): LocationLevel;

    /** Human-readable name of this specific instance, e.g. "Western Cape". */
    public function locationLabel(): string;

    /** Id of the parent locatable; null for Country. */
    public function locationParentId(): ?int;
}
