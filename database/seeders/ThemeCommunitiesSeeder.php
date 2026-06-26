<?php

namespace Database\Seeders;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Circles\Circle;
use App\Models\Communities\ThemeCommunity;
use App\Models\Theme;
use App\Services\Circles\CircleCreationService;
use Illuminate\Database\Seeder;

class ThemeCommunitiesSeeder extends Seeder
{
    public function __construct(
        protected CircleCreationService $circleService
    ) {
    }

    /**
     * Parent LocationCommunity circle id => theme ids to add beneath it.
     *
     * NOTE: these circle ids are environment-specific to this initial seed run
     * (do not hardcode them elsewhere).
     */
    private const PLAN = [
        7   => [12, 2, 4, 7],   // National — Justice & Crime, Democracy, Education, Health
        271 => [2, 4, 6, 7],    // Western Cape — Democracy, Education, Environment, Health
        283 => [25, 58, 9],     // Garden Route DM — Sustainable Farming, Conservation, Housing
    ];

    public function run(): void
    {
        foreach (self::PLAN as $parentCircleId => $themeIds) {
            $parentCircle  = Circle::findOrFail($parentCircleId);
            $locatableType = LocatableType::from($parentCircle->locatable_type);

            $this->command->info("Parent: {$parentCircle->name} [{$locatableType->label()} #{$parentCircle->locatable_id}]");

            foreach ($themeIds as $themeId) {
                $theme = Theme::find($themeId);

                if (! $theme) {
                    $this->command->error("  Theme id {$themeId} not found — STOPPING.");
                    throw new \RuntimeException("Theme id {$themeId} does not exist.");
                }

                if ($this->alreadyExists($themeId, $locatableType, $parentCircle->locatable_id)) {
                    $this->command->warn("  Skipped (already exists): {$theme->name}");

                    continue;
                }

                $circle = $this->circleService->create(
                    type:          CommunityType::ThemeCommunity,
                    data:          ['theme_id' => $themeId],
                    parentCircle:  $parentCircle,
                    locatableType: $locatableType,
                    locatableId:   $parentCircle->locatable_id,
                );

                $this->command->info("  Created: {$circle->name}");
            }
        }

        $this->command->info("\nTheme communities seeded successfully.");
    }

    /**
     * A ThemeCommunity circle for this theme already exists at this exact location.
     */
    private function alreadyExists(int $themeId, LocatableType $locatableType, int $locatableId): bool
    {
        $themeCommunityIds = ThemeCommunity::where('theme_id', $themeId)->pluck('id');

        if ($themeCommunityIds->isEmpty()) {
            return false;
        }

        return Circle::query()
            ->where('circleable_type', CommunityType::ThemeCommunity->value)
            ->whereIn('circleable_id', $themeCommunityIds)
            ->where('locatable_type', $locatableType->value)
            ->where('locatable_id', $locatableId)
            ->exists();
    }
}
