<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordinateData extends Model
{
    // Explicit table name: Laravel would not pluralise "CoordinateData" to
    // "coordinate_data" reliably.
    protected $table = 'coordinate_data';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    // Loose legacy reference (province_id has no FK constraint at the DB level).
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
