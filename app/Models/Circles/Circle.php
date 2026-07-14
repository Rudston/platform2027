<?php

namespace App\Models\Circles;

use App\Enums\CircleStatus;
use App\Enums\CommunityType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Circle extends Model
{
    use HasTranslations, SoftDeletes;

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
            if (!$circle->name && $circle->circleable) {
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

            if ($owner && method_exists($owner, 'defaultServices')) {
                $serviceIds = Service::whereIn('key', $owner->defaultServices())->pluck('id');

                if ($serviceIds->isNotEmpty()) {
                    $circle->services()->attach($serviceIds, ['is_active' => true]);
                }
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

        $tables = (array) config('permission.table_names');
        $columns = (array) config('permission.column_names');

        $modelHasRoles = $tables['model_has_roles'] ?? 'model_has_roles';
        $rolesTable = $tables['roles'] ?? 'roles';
        $modelKey = $columns['model_morph_key'] ?? 'model_id';
        $teamKey = $columns['team_foreign_key'] ?? 'circle_id';

        return static::query()
            ->whereIn(
                'id',
                fn ($query) => $query
                    ->select("{$modelHasRoles}.{$teamKey}")
                    ->from($modelHasRoles)
                    ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRoles}.role_id")
                    ->where("{$rolesTable}.name", 'circle_admin')
                    ->where("{$modelHasRoles}.model_type", $user->getMorphClass())
                    ->where("{$modelHasRoles}.{$modelKey}", $user->getKey())
                    ->whereNotNull("{$modelHasRoles}.{$teamKey}"),
            )
            ->get();
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
