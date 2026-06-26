<?php

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';

    protected $guarded = [];

    // The countries table has no created_at/updated_at columns.
    public $timestamps = false;

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }
    public function circleName(): string
    {
        return "National Level Community for ".$this->name;
    }

    public function circleNameShort() {
        return "National";
    }
    public function circleDescription(): string
    {
        return "This is where you will find everything that belongs to the top level of the platform for ".$this->name;
    }
}
