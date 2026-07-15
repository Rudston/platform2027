<?php

namespace App\Services\Circles\Concerns;

/**
 * Default containerComponent() for CircleServiceContract handlers that have no
 * UI surface (e.g. Email, Manage Users). Returns null.
 */
trait HasNoContainerComponent
{
    public function containerComponent(): ?string
    {
        return null;
    }
}
