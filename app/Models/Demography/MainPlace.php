<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MainPlace extends Model
{
    protected $table = 'main_places';

    protected $guarded = [];
    protected $casts = [
        'population' => 'integer',
    ];

    public function localMunicipality(): BelongsTo
    {
        return $this->belongsTo(LocalMunicipality::class);
    }
}
