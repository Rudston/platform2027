<?php

namespace App\Models\Forums;

use App\Enums\Forums\ForumDiscussionModerationStatus;
use App\Enums\Forums\ForumDiscussionStatus;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ForumGroup::class, 'forum_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
