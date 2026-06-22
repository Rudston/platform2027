<?php

/**
 * Usage:
 * $circleService = app(CircleCreationService::class);
 *
 * $circleService->create(
 * type: CommunityType::Organisation,
 * data: ['name' => 'My Organisation', 'description' => '...'],
 * );
 *
 */

namespace App\Services\Circles;

use App\Models\Circles\Circle;
use App\Models\Circles\Service;
use Illuminate\Support\Facades\DB;
use App\Enums\CommunityType;

class CircleCreationService
{
    public function create(
        CommunityType $type,       // ← enum, not raw string
        array $data,
        ?Circle $parentCircle = null
    ): Circle {
        return DB::transaction(function () use ($type, $data, $parentCircle) {

            $modelClass = $type->modelClass();
            $community  = app($modelClass)->create($data);

            // 2. Create its Circle (name/description auto-populated
            //    from getCircleName() via booted())
            $circle = Circle::create([
                'circleable_id'   => $community->id,
                'circleable_type' => $type,
                'parent_id'       => $parentCircle?->id,
            ]);

            // 3. Attach default services automatically
            $serviceIds = Service::whereIn('key', $community->defaultServices())
                ->pluck('id');
            $circle->services()->attach($serviceIds, ['is_active' => true]);

            return $circle;
        });
    }
}
