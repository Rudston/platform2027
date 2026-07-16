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

    /**
     * Whether this membership's internal_role is TRUSTED — i.e. confirmed by the
     * organisation's contact, not merely claimed.
     *
     * ALWAYS use this for elevated-access decisions (forum private-group
     * visibility, etc.). NEVER check internal_role on its own: a role may be
     * stored while its claim is still 'pending', or even 'rejected' (we keep the
     * value for audit but must not trust it). metadata.internal_role_approved
     * is the single source of truth for whether the role is honoured.
     */
    public function hasApprovedInternalRole(): bool
    {
        return $this->internal_role !== null
            && ($this->metadata['internal_role_approved'] ?? null) === 'approved';
    }
}
