<?php

namespace App\Models\Forums;

use App\Enums\Forums\ForumDiscussionModerationStatus;
use App\Enums\Forums\ForumDiscussionStatus;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function participantCount(): int
    {
        return $this->participants()->whereNull('left_at')->count();
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
