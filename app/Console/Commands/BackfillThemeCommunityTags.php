<?php

namespace App\Console\Commands;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tag each ThemeCommunity's circle with the Theme it was built from — i.e.
 * attach the circleable's theme_id as a tag (via taggables) to the wrapping
 * Circle. Idempotent (syncWithoutDetaching), adds-only, manual. A one-off /
 * occasionally re-run maintenance aid; NOT scheduled.
 */
class BackfillThemeCommunityTags extends Command
{
    protected $signature = 'circles:backfill-theme-tags';

    protected $description = "Tag each ThemeCommunity's circle with its own theme (idempotent, safe to re-run).";

    public function handle(): int
    {
        $count = 0;

        Circle::query()
            ->where('circleable_type', CommunityType::ThemeCommunity->value)
            ->with('circleable')
            ->chunkById(100, function (Collection $circles) use (&$count): void {
                foreach ($circles as $circle) {
                    $themeId = $circle->circleable?->theme_id;

                    if ($themeId === null) {
                        continue;
                    }

                    // Only count circles that didn't already carry the tag.
                    if ($circle->tags()->whereKey($themeId)->exists()) {
                        continue;
                    }

                    $circle->tags()->syncWithoutDetaching([$themeId]);
                    $count++;
                }
            });

        $this->info("{$count} theme-community circles tagged");

        return self::SUCCESS;
    }
}
