<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Fulfils the App\Contracts\Locatable contract by delegating to the model's
 * Circle, where the location is actually stored (circles.locatable_*).
 * Requires the consuming model to also use HasCircle (so $this->circle exists).
 */
trait HasLocation
{
    public function location(): MorphTo
    {
        return $this->circle->locatable();
    }

    public function locatedIn(Model $place): bool
    {
        $location = $this->circle?->locatable;

        return $location !== null
            && $location::class === $place::class
            && $location->getKey() === $place->getKey();
    }

    public function setLocation(Model $place): void
    {
        $circle = $this->circle;
        $circle->locatable()->associate($place);
        $circle->save();
    }
}
