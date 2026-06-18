<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $table = 'provinces';

    protected $guarded = [];

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
}
