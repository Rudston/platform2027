<?php

namespace App\Models\Forums;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's participation in a forum discussion. Rows are never deleted — a
 * participation is "closed" by setting left_at (mirrors CircleMembership).
 * left_at === null means currently active.
 */
class ForumDiscussionParticipant extends Model
{
    protected $fillable = [
        'forum_discussion_id',
        'user_id',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(ForumDiscussion::class, 'forum_discussion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Currently-active participations (not yet left). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
