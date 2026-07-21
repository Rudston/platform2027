<?php

namespace App\Models\Forums;

use App\Enums\Forums\ForumDiscussionModerationStatus;
use App\Enums\Forums\ForumDiscussionStatus;
use App\Models\Comment;
use App\Models\Concerns\HasTags;
use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumDiscussion extends Model
{
    use HasTags, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => ForumDiscussionStatus::class,
        'moderation_status' => ForumDiscussionModerationStatus::class,
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'content_edited_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ForumGroup::class, 'forum_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** True once the author has edited the first post's content (drives "(Edited)"). */
    public function isEdited(): bool
    {
        return $this->content_edited_at !== null;
    }

    /** Only the discussion's author may edit the first post's content. */
    public function canEditContentBy(?User $user): bool
    {
        return $user !== null
            && $this->created_by !== null
            && $this->created_by === $user->getKey();
    }

    /*
    |--------------------------------------------------------------------------
    | Comments (the reply engine) & likes
    |--------------------------------------------------------------------------
    */

    /** All comments attached to this discussion (root comments + replies). */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Cosmetic alias for comments() — "posts" reads more naturally in forum
     * context. IDENTICAL relation/rows, zero duplicate logic.
     */
    public function posts(): MorphMany
    {
        return $this->comments();
    }

    /** Likes on the discussion itself (generic likeable — no UI yet). */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /*
    |--------------------------------------------------------------------------
    | Participation (join / leave — never deleted, closed via left_at)
    |--------------------------------------------------------------------------
    */

    public function participants(): HasMany
    {
        return $this->hasMany(ForumDiscussionParticipant::class);
    }

    /** @return Collection<int, ForumDiscussionParticipant> */
    public function activeParticipants(): Collection
    {
        return $this->participants()->whereNull('left_at')->get();
    }

    /**
     * The number of participants: the unique users who have contributed to the
     * discussion — its creator plus everyone who has posted a comment, counted
     * ONCE each (the creator commenting doesn't count her twice).
     *
     * NOTE: this is a derived contribution metric, distinct from the explicit
     * join/leave subscription in participants() / activeParticipants().
     */
    public function participantCount(): int
    {
        return static::participantCountsFor([$this])[$this->getKey()] ?? 0;
    }

    /**
     * Participant count per discussion (creator ∪ its distinct commenters,
     * unique within each discussion) for a set of discussions, resolved in a
     * SINGLE comments query — so callers summing across a group / circle avoid
     * an N+1. Each discussion must carry at least `id` and `created_by`.
     *
     * @param  \Illuminate\Support\Collection<int, self>|array<int, self>  $discussions
     * @return array<int, int> [discussion_id => participant count]
     */
    public static function participantCountsFor(iterable $discussions): array
    {
        $discussions = collect($discussions);
        $ids = $discussions->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        // [discussion_id => [distinct commenter user_ids]] in one query.
        $commenters = Comment::query()
            ->where('commentable_type', (new self)->getMorphClass())
            ->whereIn('commentable_id', $ids)
            ->whereNotNull('user_id')
            ->get(['commentable_id', 'user_id'])
            ->groupBy('commentable_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->unique()->all());

        $counts = [];
        foreach ($discussions as $d) {
            $users = $commenters->get($d->id, []);

            // Fold the creator into the set (once — skip if she also commented).
            if ($d->created_by !== null && ! in_array($d->created_by, $users, true)) {
                $users[] = $d->created_by;
            }

            $counts[$d->id] = count($users);
        }

        return $counts;
    }

    public function isJoinedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->participants()
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();
    }

    /** Join (idempotent — returns the existing active participation if any). */
    public function join(User $user): ForumDiscussionParticipant
    {
        $existing = $this->participants()
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->participants()->create([
            'user_id' => $user->getKey(),
            'joined_at' => now(),
        ]);
    }

    /** Leave: close the user's active participation (no-op if not joined). */
    public function leave(User $user): void
    {
        $this->participants()
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->update(['left_at' => now()]);
    }

    /**
     * Tagging rights: the discussion's author, OR a manager of the owning
     * group's circle (circle_admin / admin / superadmin). Discussion-level edit
     * rights weren't formally specified (CRUD deferred) — this is the confirmed
     * interim rule.
     */
    public function canBeTaggedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->created_by !== null && $this->created_by === $user->getKey()) {
            return true;
        }

        return $this->group?->circle?->isManageableBy($user) ?? false;
    }
}
