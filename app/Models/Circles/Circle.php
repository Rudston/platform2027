<?php

namespace App\Models\Circles;

use App\Contracts\Circles\HasDefaultServices;
use App\Contracts\Communities\HasMembershipRules;
use App\Enums\CircleStatus;
use App\Enums\CommunityType;
use App\Models\Communication\Request;
use App\Models\Communities\ThemeCommunity;
use App\Models\Concerns\HasTags;
use App\Models\Forums\ForumGroup;
use App\Models\User;
use App\Services\Communication\EmailServiceHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Translatable\HasTranslations;

class Circle extends Model
{
    use HasTags, HasTranslations, SoftDeletes;

    /** Translatable JSON columns (resolved to the current locale transparently). */
    public array $translatable = ['description'];

    protected $guarded = [];

    protected $casts = [
        'depth' => 'integer',
        'status' => CircleStatus::class,
    ];

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'depth',
        'path',
        'circleable_id',
        'circleable_type',
        'locatable_id',
        'locatable_type',
        'is_test',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function circleable(): MorphTo
    {
        return $this->morphTo();
    }

    public function locatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot(['config', 'is_active'])
            ->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Circle::class, 'parent_id');
    }

    /**
     * Communities this circle is additionally linked to (beyond its parent).
     */
    public function associations(): BelongsToMany
    {
        return $this->belongsToMany(
            Circle::class,
            'circle_associations',
            'circle_id',
            'associated_circle_id'
        )->withPivot(
            'association_type',
            'approved',
            'approved_at',
            'approved_by_user_id'
        )->withTimestamps();
    }

    /**
     * Circles that have linked themselves to this circle.
     */
    public function associatedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            Circle::class,
            'circle_associations',
            'associated_circle_id',
            'circle_id'
        )->withPivot(
            'association_type',
            'approved',
            'approved_at',
            'approved_by_user_id'
        )->withTimestamps();
    }

    /**
     * Approved-only convenience scopes over the association relationships.
     */
    public function approvedAssociations(): BelongsToMany
    {
        return $this->associations()
            ->wherePivot('approved', true);
    }

    public function approvedAssociatedBy(): BelongsToMany
    {
        return $this->associatedBy()
            ->wherePivot('approved', true);
    }

    /**
     * Ancestor circles, resolved from the materialised path (excludes self).
     */
    public function ancestors(): Collection
    {
        if (! $this->path) {
            return new Collection;
        }

        $ids = explode('/', $this->path);
        array_pop($ids); // drop self (the last segment)

        if (empty($ids)) {
            return new Collection;
        }

        return static::whereIn('id', $ids)->orderBy('depth')->get();
    }

    /**
     * Descendant circles, found via the materialised path prefix.
     */
    public function descendants(): Collection
    {
        if (! $this->path) {
            return new Collection;
        }

        return static::where('path', 'like', $this->path.'/%')->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function isNestedIn(Circle $circle): bool
    {
        return str_starts_with((string) $this->path, $circle->path.'/');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** Limit the query to active circles only. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CircleStatus::Active);
    }

    /**
     * Limit to circles the given user may see: active always, plus pending for
     * platform admins/superadmins. Column is qualified so the scope is safe on
     * relationship (joined) queries too.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $statuses = array_map(
            fn (CircleStatus $status): string => $status->value,
            static::visibleStatusesFor($user),
        );

        return $query->whereIn('circles.status', $statuses);
    }

    /**
     * The circle statuses a given user may see: active always, plus pending for
     * platform admins/superadmins. Single source of truth for scopeVisibleTo()
     * and isVisibleTo().
     *
     * @return list<CircleStatus>
     */
    public static function visibleStatusesFor(?User $user): array
    {
        $statuses = [CircleStatus::Active];

        if ($user?->hasAnyRole(['admin', 'superadmin'])) {
            $statuses[] = CircleStatus::Pending;
        }

        return $statuses;
    }

    /** Whether this circle is visible to the given user (see visibleStatusesFor). */
    public function isVisibleTo(?User $user): bool
    {
        return in_array($this->status, static::visibleStatusesFor($user), true);
    }

    /*
    |--------------------------------------------------------------------------
    | Membership (join / leave, per-type limits)
    |--------------------------------------------------------------------------
    */

    public function memberships(): HasMany
    {
        return $this->hasMany(CircleMembership::class);
    }

    /** The active (not-yet-left) membership for this circle + user, if any. */
    public function activeMembership(User $user): ?CircleMembership
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();
    }

    /**
     * Whether $user may join this circle, and — if at the per-type cap — which
     * of their existing memberships are old enough to swap out.
     *
     * @return array{allowed: bool, reason: ?string, available_at: ?Carbon, swappable: \Illuminate\Support\Collection}
     */
    public function canUserJoin(User $user): array
    {
        $ok = ['allowed' => true, 'reason' => null, 'available_at' => null, 'swappable' => collect()];

        // Global admins/superadmins bypass all checks (NOT circle_admin).
        if ($user->hasRole('admin') || $user->hasRole('superadmin')) {
            return $ok;
        }

        $owner = $this->circleable;

        if (! $owner instanceof HasMembershipRules) {
            return $ok;
        }

        // Active memberships this user holds of the SAME community type.
        $active = CircleMembership::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->whereHas('circle', fn (Builder $q) => $q->where('circleable_type', $this->circleable_type))
            ->get();

        if ($active->count() < $owner->maxConcurrentMemberships()) {
            return $ok;
        }

        // At cap: memberships held long enough to be swapped out.
        $months = $owner->minMembershipMonthsBeforeSwitch();
        $threshold = now()->subMonths($months);
        $swappable = $active->filter(fn (CircleMembership $m) => $m->joined_at <= $threshold)->values();

        if ($swappable->isNotEmpty()) {
            return ['allowed' => true, 'reason' => null, 'available_at' => null, 'swappable' => $swappable];
        }

        // None swappable yet — the earliest becomes eligible this far out.
        $availableAt = $active->min('joined_at')?->copy()->addMonths($months);

        return [
            'allowed' => false,
            'reason' => 'membership_hold',
            'available_at' => $availableAt,
            'swappable' => collect(),
        ];
    }

    /**
     * Create an active membership for $user. Validates $internalRole against the
     * circleable's allowedInternalRoles(), re-checks eligibility server-side
     * (unless $skipChecks — used only for direct grants, e.g. an approved
     * organisation's own creator), and closes $dropMembership if a swap is used.
     */
    public function joinAsMember(
        User $user,
        ?string $internalRole = null,
        ?CircleMembership $dropMembership = null,
        bool $skipChecks = false,
    ): CircleMembership {
        $owner = $this->circleable;
        $allowedRoles = $owner instanceof HasMembershipRules ? $owner->allowedInternalRoles() : [];

        if ($internalRole !== null && ! in_array($internalRole, $allowedRoles, true)) {
            throw new \InvalidArgumentException("Internal role [{$internalRole}] is not allowed for this community.");
        }

        // Idempotent: already an active member.
        if ($existing = $this->activeMembership($user)) {
            return $existing;
        }

        if (! $skipChecks && ! $this->canUserJoin($user)['allowed']) {
            throw new \RuntimeException('User is not eligible to join this circle.');
        }

        // A claimed 'organisation_member' role from a normal join (NOT the
        // trusted approval-hook grant, which uses skipChecks) must be confirmed
        // by the org contact. The user becomes a member immediately; only the
        // role is gated as 'pending'.
        $needsClaim = $internalRole === 'organisation_member' && ! $skipChecks;

        // An assigned internal role carries an approval status: a trusted direct
        // grant (skipChecks — e.g. the org creator/admin at approval time) is
        // 'approved' immediately; a normal claim starts 'pending' until the org
        // contact confirms it. No role → no status.
        $roleStatus = $internalRole === null ? null : ($skipChecks ? 'approved' : 'pending');

        $membership = DB::transaction(function () use ($user, $internalRole, $dropMembership, $roleStatus): CircleMembership {
            if ($dropMembership !== null && $dropMembership->left_at === null) {
                $dropMembership->update(['left_at' => now()]);
            }

            return $this->memberships()->create([
                'user_id' => $user->id,
                'internal_role' => $internalRole,
                'joined_at' => now(),
                'metadata' => $roleStatus !== null ? ['internal_role_approved' => $roleStatus] : null,
            ]);
        });

        if ($needsClaim) {
            $this->requestInternalRoleClaim($user, $membership);
        }

        return $membership;
    }

    /**
     * Open an external approval request for a claimed internal role and email
     * the organisation's contact (mirrors the organisation-approval email). The
     * membership already exists with internal_role_approved = 'pending'.
     */
    protected function requestInternalRoleClaim(User $user, CircleMembership $membership): void
    {
        $owner = $this->circleable;
        $organisation = (is_object($owner) && method_exists($owner, 'organisation'))
            ? $owner->organisation
            : null;
        $contactEmail = $organisation?->contact_email;

        if (! $contactEmail) {
            return; // no contact to confirm with — the claim stays pending
        }

        $request = Request::createForMemberClaim($user, $this, $membership, $contactEmail);

        $variables = [
            'contact_name' => (string) ($organisation->contact_person ?? ''),
            'organisation_name' => (string) ($organisation->name ?? $this->name),
            'claimer_name' => (string) $user->name,
            'review_url' => route('requests.confirm', $request->token),
            'expires_at' => $request->token_expires_at->format('d M Y'),
        ];

        try {
            app(EmailServiceHandler::class)->sendTemplate('email.organisation_member_claim_request', $contactEmail, $variables);
            $request->logEmail('email.organisation_member_claim_request', $contactEmail, 'sent');
        } catch (\Throwable $e) {
            $request->logEmail('email.organisation_member_claim_request', $contactEmail, 'failed', $e->getMessage());
        }
    }

    /**
     * Voluntarily leave: close the user's active membership. No time/count
     * limits gate leaving — those only gate joining a new membership.
     */
    public function leave(User $user): void
    {
        $this->activeMembership($user)?->update(['left_at' => now()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Model events: maintain depth/path and attach default services
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        // Set depth from the parent before the row is inserted.
        static::creating(function (Circle $circle) {
            if ($circle->parent_id && $parent = static::find($circle->parent_id)) {
                $circle->depth = $parent->depth + 1;
            }
            if (! $circle->name && $circle->circleable) {
                $circle->name = $circle->circleable->getCircleName();
                $circle->description = $circle->circleable->getCircleDescription();
            }
        });

        // Once the id exists, build the materialised path, then attach the
        // owner's default services.
        static::created(function (Circle $circle) {
            $parent = $circle->parent;

            $circle->path = $parent
                ? $parent->path.'/'.$circle->id
                : (string) $circle->id;

            $circle->saveQuietly();

            $owner = $circle->circleable;

            // Attach the circleable's default services in the order it declares
            // them (only circleables that opt in via HasDefaultServices).
            if ($owner instanceof HasDefaultServices) {
                $servicesByKey = Service::whereIn('key', $owner->defaultServices())
                    ->get()
                    ->keyBy('key');

                foreach ($owner->defaultServices() as $key) {
                    if ($service = $servicesByKey->get($key)) {
                        $circle->services()->attach($service->id, ['is_active' => true]);
                    }
                }
            }

            // A ThemeCommunity's circle is auto-tagged with the Theme it was
            // built from (mirrors circles:backfill-theme-tags for new circles).
            // Guarded on theme_id so no taggables query fires when absent.
            if ($owner instanceof ThemeCommunity && $owner->theme_id !== null) {
                $circle->tags()->syncWithoutDetaching([$owner->theme_id]);
            }
        });
    }

    /**
     * Users who hold the 'circle_admin' role scoped to THIS circle.
     *
     * Spatie runs in teams mode with the team id stored in
     * model_has_roles.circle_id, so we query that pivot directly rather than via
     * the roles() relationship (which is scoped to the *current* permissions
     * team, not this circle). A circle can have any number of admins (none
     * initially); a user can be a circle_admin of several circles.
     *
     * @return Collection<int, User>
     */
    public function administrators(): Collection
    {
        $tables = (array) config('permission.table_names');
        $columns = (array) config('permission.column_names');

        $modelHasRoles = $tables['model_has_roles'] ?? 'model_has_roles';
        $rolesTable = $tables['roles'] ?? 'roles';
        $modelKey = $columns['model_morph_key'] ?? 'model_id';
        $teamKey = $columns['team_foreign_key'] ?? 'circle_id';

        return User::query()
            ->whereIn(
                (new User)->getKeyName(),
                fn ($query) => $query
                    ->select("{$modelHasRoles}.{$modelKey}")
                    ->from($modelHasRoles)
                    ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRoles}.role_id")
                    ->where("{$rolesTable}.name", 'circle_admin')
                    ->where("{$modelHasRoles}.model_type", (new User)->getMorphClass())
                    ->where("{$modelHasRoles}.{$teamKey}", $this->id),
            )
            ->get();
    }

    /**
     * Circles the given user administers — the inverse of administrators().
     *
     * Returns every circle on which the user holds the 'circle_admin' role,
     * queried directly off model_has_roles (Spatie teams mode scopes the
     * roles() relationship to the *current* team, so it can't answer "which
     * teams does this user hold this role on"). A user may administer zero or
     * many circles.
     *
     * @return Collection<int, Circle>
     */
    public static function administeredBy(?User $user): Collection
    {
        if ($user === null) {
            return new Collection;
        }

        return static::query()
            ->whereIn('id', static::administeredCircleIdsSubquery($user))
            ->get();
    }

    /**
     * The ONE place the "is a circle_admin of this circle" rule is expressed: a
     * subquery selecting the circle ids on which $user holds the `circle_admin`
     * role. Reused by administeredBy() (single-record) and scopeManageableBy()
     * (query scope) so the authorization rule never diverges. Spatie teams mode
     * scopes the roles() relationship to the *current* team, so we read
     * model_has_roles directly.
     */
    protected static function administeredCircleIdsSubquery(User $user): \Closure
    {
        $tables = (array) config('permission.table_names');
        $columns = (array) config('permission.column_names');

        $modelHasRoles = $tables['model_has_roles'] ?? 'model_has_roles';
        $rolesTable = $tables['roles'] ?? 'roles';
        $modelKey = $columns['model_morph_key'] ?? 'model_id';
        $teamKey = $columns['team_foreign_key'] ?? 'circle_id';

        return fn ($query) => $query
            ->select("{$modelHasRoles}.{$teamKey}")
            ->from($modelHasRoles)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRoles}.role_id")
            ->where("{$rolesTable}.name", 'circle_admin')
            ->where("{$modelHasRoles}.model_type", $user->getMorphClass())
            ->where("{$modelHasRoles}.{$modelKey}", $user->getKey())
            ->whereNotNull("{$modelHasRoles}.{$teamKey}");
    }

    /** A global manage role (admin/superadmin) — bypasses per-circle scoping. */
    protected static function isPrivilegedManager(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['admin', 'superadmin']);
    }

    /**
     * Whether $user may MANAGE this circle: a global admin/superadmin, or a
     * circle_admin of THIS circle. The single-record authorization check reused
     * by both Filament and public-facing components (e.g. the Forums tab); its
     * query counterpart is scopeManageableBy().
     */
    public function isManageableBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return static::isPrivilegedManager($user) || $this->isAdministeredBy($user);
    }

    /**
     * Constrain a Circle query to those $user may MANAGE — everything for a
     * global admin/superadmin, else only circles they are a circle_admin of
     * (none for a guest). The query counterpart to isManageableBy(), sharing the
     * same underlying rule (administeredCircleIdsSubquery / isPrivilegedManager).
     */
    public function scopeManageableBy(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if (static::isPrivilegedManager($user)) {
            return $query;
        }

        return $query->whereIn('id', static::administeredCircleIdsSubquery($user));
    }

    /** Tagging rights mirror manage rights (uniform hook used by the tag picker). */
    public function canBeTaggedBy(?User $user): bool
    {
        return $this->isManageableBy($user);
    }

    /**
     * Whether $user holds the circle_admin role scoped to THIS circle
     * (distinct from isManageableBy, which is also true for global admins who
     * are NOT circle_admins here).
     */
    public function isAdministeredBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return static::administeredBy($user)->contains(fn (Circle $c) => $c->id === $this->id);
    }

    /**
     * Grant $user the circle_admin role scoped to THIS circle (in addition to
     * any existing admins). Idempotent — Spatie won't duplicate the assignment.
     */
    public function addAdministrator(User $user): void
    {
        setPermissionsTeamId($this->id);
        $user->assignRole('circle_admin');
        setPermissionsTeamId(null);
    }

    /**
     * Revoke $user's circle_admin role scoped to THIS circle. Callers must
     * ensure another admin remains (see CommunityPage) — a circle should never
     * be left without one via self-removal.
     */
    public function removeAdministrator(User $user): void
    {
        setPermissionsTeamId($this->id);
        $user->removeRole('circle_admin');
        setPermissionsTeamId(null);
    }

    /** Forum groups created under this circle's Forums tab. */
    public function forumGroups(): HasMany
    {
        return $this->hasMany(ForumGroup::class);
    }

    /**
     * Resolve the platform user responsible for a circle (for escalation /
     * notification / internal routing).
     *
     * Walks up the tree — the circle itself, then its ancestors nearest-first —
     * and returns the first circle_admin of the nearest LocationCommunity that
     * has one. If no such location admin exists, falls back to the first global
     * 'admin', then the first 'superadmin'. Null only if the platform has none
     * of those.
     */
    public static function responsibleAdminFor(Circle $circle): ?User
    {
        // The circle plus its ancestors, ordered nearest → root.
        $chain = $circle->ancestors()->reverse()->prepend($circle);

        foreach ($chain as $node) {
            if ($node->circleable_type !== CommunityType::LocationCommunity->value) {
                continue;
            }

            if ($admin = $node->administrators()->first()) {
                return $admin;
            }
        }

        return static::firstUserWithRole('admin')
            ?? static::firstUserWithRole('superadmin');
    }

    /** First user (lowest id) holding the given role, or null. */
    private static function firstUserWithRole(string $role): ?User
    {
        $tables = (array) config('permission.table_names');
        $columns = (array) config('permission.column_names');

        $modelHasRoles = $tables['model_has_roles'] ?? 'model_has_roles';
        $rolesTable = $tables['roles'] ?? 'roles';
        $modelKey = $columns['model_morph_key'] ?? 'model_id';

        return User::query()
            ->whereIn(
                (new User)->getKeyName(),
                fn ($query) => $query
                    ->select("{$modelHasRoles}.{$modelKey}")
                    ->from($modelHasRoles)
                    ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRoles}.role_id")
                    ->where("{$rolesTable}.name", $role)
                    ->where("{$modelHasRoles}.model_type", (new User)->getMorphClass()),
            )
            ->orderBy((new User)->getKeyName())
            ->first();
    }
}
