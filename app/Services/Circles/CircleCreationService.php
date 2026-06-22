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

            // 2. Create its Circle. Circle::booted() auto-populates
            //    name/description from getCircleName() and attaches the
            //    owner's defaultServices() — so we do NOT re-attach here.
            $circle = Circle::create([
                'circleable_id'   => $community->id,
                'circleable_type' => $type->value,
                'parent_id'       => $parentCircle?->id,
            ]);

            return $circle;
        });
    }
}
