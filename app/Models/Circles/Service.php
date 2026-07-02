<?php

namespace App\Models\Circles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Service extends Model
{
    use HasTranslations;

    /** Translatable JSON columns (resolved to the current locale transparently). */
    public array $translatable = ['name'];

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
