<?php

namespace App\Traits;

use App\Models\Circles\Circle;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Fulfils the App\Contracts\Circleable contract for any model that owns a
 * Circle. Use alongside `implements Circleable` on the consuming model.
 */
trait HasCircle
{
    public function circle(): HasOne
    {
        return $this->hasOne(Circle::class, 'circleable_id')
            ->where('circleable_type', static::class);
    }

    public function hasService(string $serviceKey): bool
    {
        return $this->circle->services()
            ->where('key', $serviceKey)
            ->exists();
    }

    public function defaultServices(): array
    {
        return [];
    }

    public function isNestedIn(Circle $circle): bool
    {
        return $this->circle->isNestedIn($circle);
    }

    /**
     * Run a callback within this model's circle (team) permission context,
     * resetting the team context afterwards.
     */
    public function withCirclePermissions(callable $callback): mixed
    {
        setPermissionsTeamId($this->circle->id);

        $result = $callback();

        setPermissionsTeamId(null); // reset after

        return $result;
    }
}
