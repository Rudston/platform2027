<?php

namespace App\Console\Commands;

use App\Contracts\Circles\HasDefaultServices;
use App\Models\Circles\Circle;
use App\Models\Circles\Service;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class BackfillCircleServices extends Command
{
    protected $signature = 'circles:backfill-services';

    protected $description = 'Attach any missing default services to circles whose circleable declares them (idempotent, safe to re-run).';

    public function handle(): int
    {
        $count = 0;

        Circle::query()
            ->with('circleable')
            // chunkById paginates by id, and we only ADD pivot rows (never
            // change the filtered set), so re-runs and mid-run changes never
            // skip or double-process a circle.
            ->chunkById(100, function (Collection $circles) use (&$count): void {
                foreach ($circles as $circle) {
                    $owner = $circle->circleable;

                    // Only touch circleables that opt into default services.
                    if (! $owner instanceof HasDefaultServices) {
                        continue;
                    }

                    $wanted = $owner->defaultServices();

                    if (empty($wanted)) {
                        continue;
                    }

                    // Attach only the keys not already present (any pivot state).
                    $existing = $circle->services()->pluck('key')->all();
                    $missing = array_values(array_diff($wanted, $existing));

                    if (empty($missing)) {
                        continue;
                    }

                    $servicesByKey = Service::whereIn('key', $missing)->get()->keyBy('key');

                    foreach ($missing as $key) {
                        if ($service = $servicesByKey->get($key)) {
                            $circle->services()->attach($service->id, ['is_active' => true]);
                        }
                    }

                    $count++;
                }
            });

        $this->info("{$count} circles updated");

        return self::SUCCESS;
    }
}
