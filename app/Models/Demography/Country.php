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
}
