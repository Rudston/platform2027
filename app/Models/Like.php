<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A user's like of any likeable entity (a Comment today, reusable for a
 * Discussion, Circle, etc. later). One row per (likeable, user) — enforced by
 * a unique index.
 */
class Like extends Model
{
    protected $guarded = [];

    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
