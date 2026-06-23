<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalMunicipality extends Model
{
    protected $table = 'local_municipalities';

    protected $guarded = [];

    protected $casts = [
        'population' => 'integer',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function districtMunicipality(): BelongsTo
    {
        return $this->belongsTo(DistrictMunicipality::class);
    }

    public function mainPlaces(): HasMany
    {
        return $this->hasMany(MainPlace::class);
    }
}
