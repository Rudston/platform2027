<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * A generic comment attached to any commentable entity (a ForumDiscussion
 * today, potentially others later). ONE model, ONE table — "posts" vs
 * "comments" is presentation only; never fork this into a Post model.
 *
 * Self-nesting via parent_id: null = root comment, non-null = a reply at any
 * depth. Pinning is only valid for root comments (guarded below).
 */
class Comment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'pinned' => 'boolean',
        'pinned_position' => 'integer',
        'hidden' => 'boolean',
        'flagged_as_offensive' => 'boolean',
        'moderated' => 'boolean',
    ];

    protected static function booted(): void
    {
        // A reply (non-root comment) can never be pinned — refuse loudly rather
        // than let a pin silently succeed.
        static::saving(function (Comment $comment): void {
            if ($comment->pinned && $comment->parent_id !== null) {
                throw new LogicException('A reply (non-root comment) cannot be pinned.');
            }
        });
    }

    /** The entity this comment is attached to (e.g. a ForumDiscussion). */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The parent comment (null for a root comment). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Direct child replies only (not a recursive tree — that's a UI concern). */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The moderator who actioned this comment, if any. */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /** True for a top-level comment (no parent). */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
