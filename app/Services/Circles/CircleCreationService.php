<?php

/**
 * Usage:
 * $circleService = app(CircleCreationService::class);
 *
 * $circleService->create(
 *     type: CommunityType::Organisation,
 *     data: ['name' => 'My Organisation', 'description' => '...'],
 *     // location is optional; defaults to South Africa (Country #191):
 *     locatableType: LocatableType::Province,
 *     locatableId: 3,
 * );
 */

namespace App\Services\Circles;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Circles\Circle;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;

class CircleCreationService
{
    /**
     * Default country id used when no location is specified (South Africa).
     */
    private const DEFAULT_COUNTRY_ID = 191;

    public function create(
        CommunityType $type,
        array $data,
        ?Circle $parentCircle = null,
        ?LocatableType $locatableType = null,
        ?int $locatableId = null,
    ): Circle {
        // Default location: country level, South Africa.
        $locatableType ??= LocatableType::Country;

        if ($locatableId === null) {
            if ($locatableType !== LocatableType::Country) {
                throw new \InvalidArgumentException(
                    "A locatableId is required for location type {$locatableType->label()}."
                );
            }
            $locatableId = self::DEFAULT_COUNTRY_ID;
        }

        // ThemeCommunity names/describes from BOTH its theme and its location.
        // The community model can't self-derive this at creation time, so set it here.
        if ($type === CommunityType::ThemeCommunity) {
            if (empty($data['theme_id'])) {
                throw new \InvalidArgumentException('theme_id is required for ThemeCommunity.');
            }

            $theme     = Theme::findOrFail($data['theme_id']);
            $locatable = app($locatableType->value)->findOrFail($locatableId);

            $data['name']        ??= $theme->name.' ('.$locatable->circleNameShort().')';
            $data['description'] ??= 'This community based in '.$locatable->circleNameShort().' focuses on '.$theme->name;
        }

        return DB::transaction(function () use ($type, $data, $parentCircle, $locatableType, $locatableId) {
            $modelClass = $type->modelClass();
            $community  = app($modelClass)->create($data);

            // Circle::booted() auto-populates name/description from
            // getCircleName() and attaches the owner's defaultServices().
            $circle = Circle::create([
                'circleable_id'   => $community->id,
                'circleable_type' => $type->value,
                'parent_id'       => $parentCircle?->id,
                'locatable_id'    => $locatableId,
                'locatable_type'  => $locatableType->value,
            ]);

            return $circle;
        });
    }
}
