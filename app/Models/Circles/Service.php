<?php

namespace App\Models\Circles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class)
            ->withPivot(['config', 'is_active'])
            ->withTimestamps();
    }
}
