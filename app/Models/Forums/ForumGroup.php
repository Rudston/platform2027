<?php

namespace App\Models\Forums;

use App\Enums\Forums\ForumGroupStatus;
use App\Enums\Forums\ForumGroupVisibility;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumGroup extends Model
{
    use HasTags, SoftDeletes;

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

    /**
     * Same target as discussions(); named for the {forumDiscussion} scoped
     * route binding (Laravel resolves the child via the pluralised parameter
     * name). discussions() is kept for withCount('discussions').
     */
    public function forumDiscussions(): HasMany
    {
        return $this->hasMany(ForumDiscussion::class);
    }

    /**
     * The group's participant count: the sum of the participant counts of all
     * its (non-deleted) child discussions (see ForumDiscussion::participantCount
     * — creator ∪ commenters, unique per discussion). This is a SUM, so a user
     * active in two of the group's discussions is counted in each. One comments
     * query total (no N+1).
     */
    public function participantCount(): int
    {
        return array_sum(ForumDiscussion::participantCountsFor(
            $this->forumDiscussions()->get(['id', 'created_by']),
        ));
    }

    /**
     * Who may start a discussion here: the group's creator, or a manager of the
     * owning circle (circle_admin / admin / superadmin). Gates the "+ Create
     * Discussion" button and the modal's save.
     */
    public function canCreateDiscussion(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->created_by !== null && $this->created_by === $user->getKey()) {
            return true;
        }

        return $this->circle?->isManageableBy($user) ?? false;
    }

    /** Tagging mirrors the group's manage rights: managers of the owning circle. */
    public function canBeTaggedBy(?User $user): bool
    {
        return $this->circle?->isManageableBy($user) ?? false;
    }

    /**
     * Whether this group is visible/discoverable to the viewer.
     *  - Public: anyone (incl. visitors).
     *  - Private: circle members (no visitors).
     *  - Internal: members holding ANY approved internal_role.
     */
    public function canView(?CircleMembership $membership, bool $isVisitor): bool
    {
        return match ($this->visibility) {
            ForumGroupVisibility::Public => true,
            ForumGroupVisibility::Private => ! $isVisitor && $membership !== null,
            ForumGroupVisibility::Internal => (bool) $membership?->hasApprovedInternalRole(),
        };
    }

    /**
     * Whether the viewer may participate (post/reply — enforced by the future
     * Discussions UI). Resolved against the visibility's participationFloor:
     * a visitor never participates; a Private floor needs any member; an
     * Internal floor needs an approved internal_role.
     */
    public function canParticipate(?CircleMembership $membership, bool $isVisitor): bool
    {
        if ($isVisitor || $membership === null) {
            return false;
        }

        return match ($this->visibility->participationFloor()) {
            ForumGroupVisibility::Internal => $membership->hasApprovedInternalRole(),
            default => true, // Private floor — any active member
        };
    }
}
