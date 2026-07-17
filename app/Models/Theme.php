<?php

namespace App\Models;

use App\Models\Circles\Circle;
use App\Models\Communities\ThemeCommunity;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Theme extends Model
{
    protected $table = 'themes';
    protected $fillable = ['name', 'parent_id', 'slug'];

    protected static function booted(): void
    {
        static::creating(function (Theme $theme): void {
            if (empty($theme->slug) && ! empty($theme->name)) {
                $theme->slug = Str::slug($theme->name);
            }
        });
    }

    // Get the immediate parent category
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    // Get immediate child subcategories
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // Recursive relationship to eager-load the entire multi-level nested tree
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Circle-INSTANTIATION relationship: the ThemeCommunity circles built FROM
     * this theme (via theme_communities.theme_id). This is DISTINCT from the
     * tag relationships below — a ThemeCommunity is a Circle whose subject is
     * this theme, whereas tags are descriptive labels attached to arbitrary
     * entities. Do not conflate the two.
     */
    public function themeCommunities(): HasMany
    {
        return $this->hasMany(ThemeCommunity::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Tagging (descriptive labels) — inverse of the HasTags trait.
    | UNRELATED to themeCommunities() above.
    |--------------------------------------------------------------------------
    */

    public function circles(): MorphToMany
    {
        return $this->morphedByMany(Circle::class, 'taggable', 'taggables');
    }

    public function forumGroups(): MorphToMany
    {
        return $this->morphedByMany(ForumGroup::class, 'taggable', 'taggables');
    }

    public function forumDiscussions(): MorphToMany
    {
        return $this->morphedByMany(ForumDiscussion::class, 'taggable', 'taggables');
    }
}
