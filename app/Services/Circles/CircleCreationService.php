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
use App\Models\Organisation;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;

class CircleCreationService
{
    /**
     * Default country id used when no location is specified (South Africa).
     */
    // TODO: review for multi-country compatibility
    private const DEFAULT_COUNTRY_ID = 191;

    public function create(
        CommunityType $type,
        array $data,
        ?Circle $parentCircle = null,
        ?LocatableType $locatableType = null,
        ?int $locatableId = null,
        ?Organisation $organisation = null,
        array $courseIds = [],
    ): Circle {
        // Inherit the parent's location when none is specified — a child circle
        // sits at its parent's location unless explicitly placed elsewhere.
        // (Seeders always pass an explicit locatable, so this only affects the
        // Explore "Add community" flow, which anchors to the selected location.)
        if ($locatableType === null && $parentCircle !== null) {
            $locatableType = LocatableType::from($parentCircle->locatable_type);
            $locatableId   = $parentCircle->locatable_id;
        }

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

        // Mirror of the inheritance above: with no explicit parent, anchor the
        // new circle under the LocationCommunity circle for its resolved
        // location. The Explore "Add community" flow represents the national
        // level as *no* selected circle (breadcrumb id null), so an
        // organisation added at country level reached here with
        // $parentCircle = null and became a second root — locatable
        // Country #191 but parent_id NULL, depth 0, path "<own id>".
        //
        // LocationCommunity is excluded on purpose: the country circle IS the
        // root and must stay parentless (LocationCommunitiesSeeder creates it
        // through this same method with no parent), and nested location circles
        // always pass an explicit parent.
        if ($parentCircle === null && $type !== CommunityType::LocationCommunity) {
            $parentCircle = $this->locationCircleFor($locatableType, $locatableId);
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

        return DB::transaction(function () use ($type, $data, $parentCircle, $locatableType, $locatableId, $organisation, $courseIds) {
            $modelClass = $type->modelClass();
            $community  = app($modelClass)->create($data);

            // Optionally link a plain Organisation entity to the
            // OrganisationCommunity (organisation_id is nullable).
            if ($type === CommunityType::Organisation && $organisation) {
                $community->update(['organisation_id' => $organisation->id]);
            }

            // Optionally attach plain Course entities to the CourseCommunity
            // (many-to-many; attaching no courses is valid).
            if ($type === CommunityType::Course && ! empty($courseIds)) {
                $community->courses()->attach($courseIds);
            }

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

    /**
     * The LocationCommunity circle anchored to the given place — the natural
     * parent for a community created at that location.
     *
     * Null when that location has no circle yet, in which case the caller
     * leaves parent_id unset rather than guessing at a different level.
     *
     * Public because `circles:backfill-root-parents` re-anchors legacy
     * parentless circles with the SAME rule — this is the one place it's
     * expressed, so the two can never diverge.
     */
    public function locationCircleFor(LocatableType $locatableType, int $locatableId): ?Circle
    {
        return Circle::query()
            ->where('circleable_type', CommunityType::LocationCommunity->value)
            ->where('locatable_type', $locatableType->value)
            ->where('locatable_id', $locatableId)
            ->orderBy('id')
            ->first();
    }
}
