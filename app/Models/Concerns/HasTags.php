<?php

namespace App\Models\Concerns;

use App\Models\Theme;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Adds a descriptive Theme-based tagging layer to a model (Circle, ForumGroup,
 * ForumDiscussion). These tags are a lightweight labelling system via the
 * `taggables` pivot — UNRELATED to ThemeCommunity / Theme::themeCommunities()
 * (the Circle-instantiation relationship).
 */
trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Theme::class, 'taggable', 'taggables');
    }
}
