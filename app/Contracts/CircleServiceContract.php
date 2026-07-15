<?php

namespace App\Contracts;

use App\Models\Circles\Circle;

interface CircleServiceContract
{
    public function boot(Circle $circle): void;

    public function getKey(): string;

    public function getPermissions(): array;

    /**
     * FQCN of the Livewire component that renders this service's UI container,
     * or null when the service has no UI surface (e.g. Email). Handlers with no
     * UI can use the HasNoContainerComponent trait for the null default.
     */
    public function containerComponent(): ?string;
}
