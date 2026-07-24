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

        return $this->isAccessibleByPlatformAdmin($user);
    }

    /**
     * The forum-content manage gate for THIS specific group — a deliberate
     * divergence from the plain Circle::isManageableBy() used elsewhere. The
     * three-way split:
     *   - superadmin              → always (any visibility);
     *   - this circle's own       → always (any visibility, Internal included);
     *     circle_admin
     *   - a global platform admin → only when this group is NOT Internal
     *     (i.e. someone who manages via the generic admin role, not by being
     *     this circle's circle_admin, is shut out of Internal groups).
     * Everyone who is not a manager of the owning circle → false.
     *
     * Layer this on TOP of isManageableBy at forum-group/comment-scoped gates;
     * do NOT fold this restriction into isManageableBy itself (that gate governs
     * many non-forum, circle-level actions that must stay unrestricted).
     */
    public function isAccessibleByPlatformAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $circle = $this->circle;

        // Not a manager of the owning circle at all → this gate never grants.
        // (Checked first: isManageableBy short-circuits on the in-memory
        // admin/superadmin role check, so a platform admin costs no query here.)
        if (! ($circle?->isManageableBy($user) ?? false)) {
            return false;
        }

        // Non-Internal groups: a manager keeps exactly the access isManageableBy
        // already grants — no divergence for Public/Private.
        if ($this->visibility !== ForumGroupVisibility::Internal) {
            return true;
        }

        // Internal groups: only superadmin or THIS circle's own circle_admin —
        // a plain global platform admin is excluded.
        return $user->hasRole('superadmin') || ($circle?->isAdministeredBy($user) ?? false);
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
