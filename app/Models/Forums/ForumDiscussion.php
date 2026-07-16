<?php

namespace App\Models\Forums;

use App\Enums\Forums\ForumDiscussionModerationStatus;
use App\Enums\Forums\ForumDiscussionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumDiscussion extends Model
{
    use SoftDeletes;

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
}
