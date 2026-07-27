<?php

namespace App\Console\Commands;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Circles\Circle;
use App\Services\Circles\CircleCreationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-anchor non-location circles that were left parentless.
 *
 * The Explore "Add community" flow represents the national level as *no*
 * selected circle, so a community added at country level reached
 * CircleCreationService with parentCircle = null and was written as a second
 * ROOT — locatable Country #191 but parent_id NULL, depth 0, path "<own id>".
 * With no ancestors such a circle has no geographic breadcrumb,
 * Circle::responsibleAdminFor() skips the LocationCommunity climb entirely, and
 * the circle sits outside every `path LIKE` subtree query.
 *
 * CircleCreationService now derives the parent from the resolved locatable, so
 * this only fixes rows created before that. It reuses the service's
 * locationCircleFor() so both apply the identical rule.
 *
 * LocationCommunity circles are never touched — the country circle IS the root
 * and must stay parentless.
 *
 * Idempotent (a fixed circle no longer matches), manual, NOT scheduled.
 */
class BackfillRootParents extends Command
{
    protected $signature = 'circles:backfill-root-parents {--dry-run : Report what would change without writing}';

    protected $description = 'Re-anchor parentless non-location circles under their location circle (idempotent, safe to re-run).';

    public function __construct(private readonly CircleCreationService $circleCreationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $orphans = Circle::query()
            ->whereNull('parent_id')
            ->where('circleable_type', '!=', CommunityType::LocationCommunity->value)
            ->orderBy('id')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No parentless non-location circles found — nothing to do.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;

        foreach ($orphans as $circle) {
            $locatableType = $circle->locatable_type
                ? LocatableType::tryFrom($circle->locatable_type)
                : null;

            if ($locatableType === null || $circle->locatable_id === null) {
                $this->warn("  #{$circle->id} {$circle->name} — no locatable, cannot resolve a parent; skipped");
                $skipped++;

                continue;
            }

            $parent = $this->circleCreationService->locationCircleFor($locatableType, (int) $circle->locatable_id);

            if ($parent === null) {
                $this->warn("  #{$circle->id} {$circle->name} — no location circle for {$locatableType->label()} #{$circle->locatable_id}; skipped");
                $skipped++;

                continue;
            }

            $depth = (int) $parent->depth + 1;
            $path = $parent->path.'/'.$circle->id;

            $this->line("  #{$circle->id} {$circle->name} — parent {$parent->id}, depth {$depth}, path {$path}");

            if (! $dryRun) {
                $this->reanchor($circle, $parent->id, $depth, $path);
            }

            $fixed++;
        }

        $verb = $dryRun ? 'would be re-anchored' : 're-anchored';
        $this->info("{$fixed} circles {$verb}".($skipped > 0 ? ", {$skipped} skipped" : ''));

        return self::SUCCESS;
    }

    /**
     * Write the circle's new parent/depth/path and shift any descendants onto
     * the new path prefix. Circle::booted() only maintains depth/path on
     * CREATE, so an update has to set them explicitly.
     */
    private function reanchor(Circle $circle, int $parentId, int $depth, string $path): void
    {
        DB::transaction(function () use ($circle, $parentId, $depth, $path): void {
            $oldPath = (string) $circle->path;
            $oldDepth = (int) $circle->depth;

            $circle->parent_id = $parentId;
            $circle->depth = $depth;
            $circle->path = $path;
            $circle->saveQuietly();

            if ($oldPath === '') {
                return;
            }

            // Descendants keep their relative position: re-prefix the path and
            // shift depth by however far the subtree moved.
            $shift = $depth - $oldDepth;
            $prefixLength = strlen($oldPath);

            Circle::query()
                ->where('path', 'like', $oldPath.'/%')
                ->each(function (Circle $descendant) use ($path, $prefixLength, $shift): void {
                    $descendant->path = $path.substr((string) $descendant->path, $prefixLength);
                    $descendant->depth = (int) $descendant->depth + $shift;
                    $descendant->saveQuietly();
                });
        });
    }
}
