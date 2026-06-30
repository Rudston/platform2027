<?php

namespace Database\Seeders\Circles;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Circles\Circle;
use App\Models\Demography\MainPlace;
use App\Services\Circles\CircleCreationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Extends the LocationCommunity hierarchy one level deeper, creating a
 * LocationCommunity circle for each MainPlace under its parent circle
 * (the LocalMunicipality OR City the MainPlace belongs to).
 *
 * Run manually (like the other Circles seeders):
 *   php artisan db:seed --class="Database\Seeders\Circles\MainPlaceCommunitiesSeeder"
 *
 * Idempotent: MainPlaces that already have a circle are skipped, so the
 * seeder is safe to re-run after a partial/interrupted run. Processes in
 * chunks, committing every CHUNK records (see class docblock on the
 * transaction strategy in the accompanying notes).
 */
class MainPlaceCommunitiesSeeder extends Seeder
{
    /** Records per chunk / per committed transaction. */
    private const CHUNK = 500;

    public function __construct(
        protected CircleCreationService $circleCreationService
    ) {
    }

    public function run(): void
    {
        // Preload the parent (LocalMunicipality / City) LocationCommunity circles,
        // keyed by "locatable_type|locatable_id", so the loop never queries per record.
        $parents = Circle::query()
            ->where('circleable_type', CommunityType::LocationCommunity->value)
            ->whereIn('locatable_type', [
                LocatableType::LocalMunicipality->value,
                LocatableType::City->value,
            ])
            ->get()
            ->keyBy(fn (Circle $c) => $c->locatable_type.'|'.$c->locatable_id);

        // Preload MainPlace ids that already have a circle (idempotency / resume).
        $existing = Circle::query()
            ->where('locatable_type', LocatableType::MainPlace->value)
            ->pluck('locatable_id')
            ->flip(); // isset($existing[$id]) is O(1)

        $processed = 0;
        $created = 0;
        $skippedExisting = 0;
        $skippedNoParent = 0;
        $errors = 0;

        MainPlace::query()->chunkById(self::CHUNK, function ($mainPlaces) use (
            $parents, $existing, &$processed, &$created, &$skippedExisting, &$skippedNoParent, &$errors
        ) {
            // One committed transaction per chunk (commit every CHUNK records).
            DB::transaction(function () use (
                $mainPlaces, $parents, $existing, &$processed, &$created, &$skippedExisting, &$skippedNoParent, &$errors
            ) {
                foreach ($mainPlaces as $mainPlace) {
                    $processed++;

                    // Idempotent: already has a circle → skip.
                    if (isset($existing[$mainPlace->id])) {
                        $skippedExisting++;

                        continue;
                    }

                    // Resolve the parent: LocalMunicipality OR City (never both).
                    [$parentType, $parentId] = match (true) {
                        ! empty($mainPlace->local_municipality_id) => [LocatableType::LocalMunicipality, $mainPlace->local_municipality_id],
                        ! empty($mainPlace->city_id)               => [LocatableType::City, $mainPlace->city_id],
                        default                                    => [null, null],
                    };

                    if ($parentType === null) {
                        $skippedNoParent++;
                        $this->command->warn("  Skip MainPlace #{$mainPlace->id} ({$mainPlace->name}): no local_municipality_id or city_id.");

                        continue;
                    }

                    $parentCircle = $parents->get($parentType->value.'|'.$parentId);

                    if (! $parentCircle) {
                        $skippedNoParent++;
                        $this->command->warn("  Skip MainPlace #{$mainPlace->id} ({$mainPlace->name}): no {$parentType->label()} circle for id {$parentId}.");

                        continue;
                    }

                    try {
                        $this->circleCreationService->create(
                            type:          CommunityType::LocationCommunity,
                            data:          ['name' => $mainPlace->circleName(), 'description' => $mainPlace->circleDescription()],
                            parentCircle:  $parentCircle,
                            locatableType: LocatableType::MainPlace,
                            locatableId:   $mainPlace->id,
                        );
                        $created++;
                    } catch (Throwable $e) {
                        // Isolated to this record's savepoint; the chunk continues.
                        $errors++;
                        $this->command->warn("  Failed MainPlace #{$mainPlace->id} ({$mainPlace->name}): {$e->getMessage()}");
                    }

                    if ($processed % self::CHUNK === 0) {
                        $this->command->info("  …processed {$processed} (created {$created}, existing {$skippedExisting}, no-parent {$skippedNoParent}, errors {$errors})");
                    }
                }
            });
        });

        $this->command->info("\nMainPlace communities seeding complete.");
        $this->command->info("  Processed:             {$processed}");
        $this->command->info("  Created:               {$created}");
        $this->command->info("  Skipped (existed):     {$skippedExisting}");
        $this->command->info("  Skipped (no parent):   {$skippedNoParent}");

        if ($errors > 0) {
            $this->command->warn("  Errors (create failed): {$errors}");
        }
    }
}
