<?php

namespace App\Models;

use App\Models\Circles\Circle;
use App\Models\Communities\ThemeCommunity;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Polls\Poll;
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
                $theme->slug = self::slugFor($theme->name);
            }
        });
    }

    /**
     * The slug a theme name yields — the ONE definition, used here and by
     * ThemeSuggestion::approve().
     *
     * NEVER returns an empty string, which is the opposite of the rule for
     * circle-scoped slugs (DerivesScopedSlugs::slugFor, .scratch/polls/issues/13)
     * — deliberately, because these are not the same kind of slug:
     *
     * - a forum/poll group slug IS the URL. Nobody may be handed a generated
     *   one they never chose, so an unslugabble name is REFUSED in the compose
     *   form and the person picks something else.
     * - a theme slug appears in NO route. It is an internal dedupe key, and
     *   nobody ever sees or types it, so there is no one to refuse and nothing
     *   to explain. Refusing here would reject a perfectly good tag — a name in
     *   a non-Latin script is a real tag name, not a mistake.
     *
     * Empty was never a safe value: Str::slug transliterates to ASCII and
     * returns '' for a non-Latin name ('中文名字') or one made only of
     * punctuation ('???'), while themes.slug is UNIQUE. Every such theme
     * therefore collided onto the first one, so approving a second unslugabble
     * suggestion returned somebody else's Theme (.scratch/tagging/issues/01).
     *
     * The fallback is DERIVED from the name, never random, so the same tag
     * suggested twice still dedupes to one Theme. It is lower-cased and trimmed
     * first so it dedupes case-insensitively, exactly as Str::slug already does
     * for Latin names — 'Housing' and 'housing' are one tag either way.
     */
    public static function slugFor(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 't-'.substr(sha1(mb_strtolower(trim($name))), 0, 12);
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

    public function polls(): MorphToMany
    {
        return $this->morphedByMany(Poll::class, 'taggable', 'taggables');
    }
}
