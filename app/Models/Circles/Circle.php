<?php

namespace App\Models\Circles;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Circle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'depth' => 'integer',
    ];

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'depth',
        'path',
        'circleable_id',
        'circleable_type',
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
}
