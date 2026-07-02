<?php

namespace App\Models\Demography;

use App\Contracts\Geographic\HasLocationLevel;
use App\Enums\LocationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistrictMunicipality extends Model implements HasLocationLevel
{
    use SoftDeletes;

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

    public function circleNameShort() {
        if (str_contains($this->name, 'Metropolitan Municipality')) {
            return trim(str_replace('Metropolitan Municipality', 'Metro', $this->name));
        } else {
            return $this->name." DM";
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

    public function locationLevel(): LocationLevel
    {
        return LocationLevel::District;
    }

    public function locationLabel(): string
    {
        return $this->name;
    }

    public function locationParentId(): ?int
    {
        return $this->province_id;
    }
}
