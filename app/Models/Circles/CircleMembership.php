<?php

namespace App\Models\Circles;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's membership of a circle. Rows are never deleted — a membership is
 * "closed" by setting left_at. left_at === null means currently active.
 */
class CircleMembership extends Model
{
    protected $fillable = [
        'circle_id',
        'user_id',
        'internal_role',
        'joined_at',
        'left_at',
        'metadata',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Currently-active memberships (not yet left). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
