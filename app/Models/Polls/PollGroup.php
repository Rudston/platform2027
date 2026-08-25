<?php

namespace App\Models\Polls;

use App\Models\Circles\Circle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of related Polls belonging to one Circle — "2027 Budget
 * Consultation". Something a user navigates INTO, not merely a heading.
 *
 * ORGANISATIONAL ONLY: a group has no visibility and no status, and never
 * gates the Polls inside it — who may respond, and who may see a Result, are
 * answered by the Poll alone. Archived, never deleted. See docs/adr/0003.
 */
class PollGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
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

    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    /** Groups still on the shelf, in display order. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Who may create, rename or archive this group: a manager of the owning
     * circle. Creating a group is a circle-level act, so this is the plain
     * circle gate — there is no per-group divergence here (unlike forums,
     * where Internal visibility narrows it), because a group has no visibility.
     */
    public function isManageableBy(?User $user): bool
    {
        return $this->circle?->isManageableBy($user) ?? false;
    }
}
