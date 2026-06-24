<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistrictMunicipality extends Model
{
    protected $table = 'district_municipalities';

    protected $guarded = [];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function mainCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'main_city_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function localMunicipalities(): HasMany
    {
        return $this->hasMany(LocalMunicipality::class);
    }

    public function circleName(): string
    {
        if (str_contains($this->name, 'Metropolitan Municipality')) {
            return "Community for the ".trim(str_replace('Metropolitan Municipality', 'Metro', $this->name));
        } else {
            return "Community for the ".$this->name." District";
        }
    }

    public function circleDescription(): string
    {
        if (str_contains($this->name, 'Metropolitan Municipality')) {
            return "This is where you will find everything relating to the ".trim(str_replace('Metropolitan Municipality', 'Metro', $this->name));
        } else {
            return "This is where you will find everything relating to the district of ".$this->name;
        }
    }
}
