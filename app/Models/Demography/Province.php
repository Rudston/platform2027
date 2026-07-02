<?php

namespace App\Models\Demography;

use App\Contracts\Geographic\HasLocationLevel;
use App\Enums\LocationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model implements HasLocationLevel
{
    protected $table = 'provinces';

    protected $guarded = [];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function districtMunicipalities(): HasMany
    {
        return $this->hasMany(DistrictMunicipality::class);
    }

    public function localMunicipalities(): HasMany
    {
        return $this->hasMany(LocalMunicipality::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function circleName(): string
    {
       if ($this->article) {
           return "Provincial Level Community for ".$this->article.$this->name;
       } else {
           return "Provincial Level Community for ".$this->name;
       }
    }

    public function circleNameShort() {
        return $this->name;
    }

    public function circleDescription(): string
    {
        if ($this->article) {
            return "This is where you will find everything relating to the top level of the province of ".$this->article." ".$this->name;
        } else {
            return "This is where you will find everything relating to the top level of the province of ".$this->name;
        }
    }

    public function locationLevel(): LocationLevel
    {
        return LocationLevel::Region;
    }

    public function locationLabel(): string
    {
        return $this->name;
    }

    public function locationParentId(): ?int
    {
        return $this->country_id;
    }
}
