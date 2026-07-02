<?php

namespace App\Models\Demography;

use App\Contracts\Geographic\HasLocationLevel;
use App\Enums\LocationLevel;
use App\Models\Circles\Circle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainPlace extends Model implements HasLocationLevel
{
    use SoftDeletes;

    protected $table = 'main_places';

    protected $guarded = [];
    protected $casts = [
        'population' => 'integer',
    ];

    public function localMunicipality(): BelongsTo
    {
        return $this->belongsTo(LocalMunicipality::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function circleName(): string
    {
        return "Local Community for ".$this->name;
    }

    public function circleNameShort() {
        return $this->name." Community";
    }

    public function circleDescription(): string
    {
        return "This is where you will find all the communities belonging to ".$this->name;
    }

    /**
     * Relative Explore URL for this MainPlace's LocationCommunity circle
     * (e.g. "/explore?circle=585"), or null if it has no circle.
     */
    public function explorerLocationCommunityUrl(): ?string
    {
        $circle = Circle::query()
            ->where('locatable_type', static::class)
            ->where('locatable_id', $this->id)
            ->first();

        return $circle
            ? route('explore', ['circle' => $circle->id], absolute: false)
            : null;
    }

    public function locationLevel(): LocationLevel
    {
        return LocationLevel::Place;
    }

    public function locationLabel(): string
    {
        return $this->name;
    }

    public function locationParentId(): ?int
    {
        // A MainPlace belongs to a LocalMunicipality OR a City — never both.
        return $this->local_municipality_id ?? $this->city_id;
    }
}
