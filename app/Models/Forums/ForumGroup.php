<?php

namespace App\Models\Forums;

use App\Enums\Forums\ForumGroupStatus;
use App\Enums\Forums\ForumGroupVisibility;
use App\Models\Circles\Circle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumGroup extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'visibility' => ForumGroupVisibility::class,
        'status' => ForumGroupStatus::class,
        'settings' => 'array',
        'archived_at' => 'datetime',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(ForumDiscussion::class);
    }
}
