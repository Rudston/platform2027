<?php

namespace App\Models\Circles;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record that a user viewed a circle's page — deduped to one row per
 * (user, circle), its last_visited_at bumped on each visit. Powers the
 * dashboard's "Recently Visited" list.
 */
class CircleVisit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_visited_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /** Record (or bump) a user's visit to a circle. Idempotent per user+circle. */
    public static function record(User $user, Circle $circle): void
    {
        static::updateOrCreate(
            ['user_id' => $user->getKey(), 'circle_id' => $circle->getKey()],
            ['last_visited_at' => now()],
        );
    }
}
