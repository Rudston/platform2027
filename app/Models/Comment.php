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
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'edited_at' => 'datetime',
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

    /**
     * Who tombstoned this comment (if soft-deleted). Kept for future audit —
     * comparing this to user_id distinguishes a self-delete from an admin
     * override; NOT surfaced in the UI yet.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
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

    /** True once a save has actually changed the content. */
    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Delete (self + admin override) & edit
    |--------------------------------------------------------------------------
    */

    /**
     * May $actor delete this comment? The author always may; otherwise a manager
     * of the owning circle (reached via the commentable → group → circle chain)
     * may, as an admin override. Mirrors ForumGroup::canCreateDiscussion.
     */
    public function canDelete(?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($this->user_id === $actor->getKey()) {
            return true;
        }

        return $this->commentable?->group?->circle?->isManageableBy($actor) ?? false;
    }

    /**
     * Delete this comment on $actor's behalf — ONE path for both self-delete and
     * admin override (the two are distinguished only by deleted_by_user_id vs
     * user_id, in the data, for later audit):
     *   - no direct replies  → HARD delete (remove the row; nothing to tombstone)
     *   - has direct replies → SOFT delete / tombstone (flag it, keep the row so
     *     its replies still resolve a valid parent)
     * Re-checks authorization defensively; a no-op if $actor may not delete.
     */
    public function deleteBy(User $actor): void
    {
        if (! $this->canDelete($actor)) {
            return;
        }

        // Likes are inaccessible once the comment is gone/tombstoned — clean them
        // up (polymorphic, so no DB cascade) rather than orphan them.
        $this->likes()->delete();

        if ($this->replies()->count() === 0) {
            $this->delete();

            return;
        }

        $this->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->getKey(),
        ]);
    }

    /** Edit is author-only (NEVER the admin override) and never on a tombstone. */
    public function canEditBy(?User $actor): bool
    {
        return $actor !== null
            && ! $this->is_deleted
            && $this->user_id === $actor->getKey();
    }

    /**
     * Author edits the content. Stamps edited_at ONLY when the content actually
     * changes (a no-op save leaves "(Edited)" off). Re-checks authorization.
     */
    public function editBy(User $actor, string $content): void
    {
        if (! $this->canEditBy($actor) || $content === $this->content) {
            return;
        }

        $this->update(['content' => $content, 'edited_at' => now()]);
    }
}
