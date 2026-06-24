<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $table = 'cities';

    protected $guarded = [];

    protected $casts = [
        'metropolis' => 'boolean',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function districtMunicipality(): BelongsTo
    {
        return $this->belongsTo(DistrictMunicipality::class);
    }

    public function urbanPlaces(): HasMany
    {
        return $this->hasMany(UrbanPlace::class);
    }

    public function mainPlaces(): HasMany
    {
        return $this->hasMany(MainPlace::class);
    }

    public function circleName(): string
    {
        return "Community for the City of ".$this->name;
    }

    public function circleDescription(): string
    {
        return "This is where you will find all the communities belonging to the municipal area of the City of ".$this->name;
    }
}
