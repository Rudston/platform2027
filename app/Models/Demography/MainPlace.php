<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainPlace extends Model
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
}
