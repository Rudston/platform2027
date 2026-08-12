<?php

namespace App\Support\Circles;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Builds the Explore-style breadcrumb trail for a single circle, so lists
 * outside the explorer (e.g. the dashboard's My Communities) show the trail a
 * viewer would see for that community IN the explorer: short place names
 * rooted at the country, each crumb linking back into the explorer at that
 * point, and — for sub-communities — a closing type crumb.
 *
 * The circle's OWN name is deliberately not a crumb: every surface that shows
 * this trail already shows the name (linked) directly beneath it.
 */
class ExplorerBreadcrumb
{
    /**
     * Name shown for the root crumb when the country circle's place is missing.
     * Mirrors the literal the explorer's own breadcrumb falls back to.
     */
    // TODO: review for multi-country compatibility (as per ExploreCommunities)
    private const COUNTRY_FALLBACK = 'South Africa';

    /**
     * @param  EloquentCollection<int, Circle>|null  $ancestors  Pre-fetched ancestors (root → parent); re-queried when omitted.
     * @return list<array{name: string, url: string}>  Every crumb is a link into the explorer.
     */
    public static function for(Circle $circle, ?EloquentCollection $ancestors = null): array
    {
        $chain = ($ancestors ?? $circle->ancestors())->loadMissing('locatable');
        $circle->loadMissing('locatable');

        // parent_id IS NULL means the root country circle (see CLAUDE.md) —
        // the explorer's national level, where the trail is a single crumb.
        if ($circle->parent_id === null) {
            return [[
                'name' => $circle->locatable?->name ?? $circle->name,
                'url'  => route('explore', absolute: false),
            ]];
        }

        // Root crumb: the explorer represents the country circle as "South
        // Africa" at national level, i.e. an Explore URL with no ?circle.
        $root = $chain->first(fn (Circle $c): bool => $c->parent_id === null);

        $crumbs = [[
            'name' => $root?->locatable?->name ?? self::COUNTRY_FALLBACK,
            'url'  => route('explore', absolute: false),
        ]];

        // Geographic trail, using the short place name ("Gauteng") rather than
        // the verbose circle name, exactly as the explorer's breadcrumb does
        // (walk every ancestor, as buildBreadcrumbForSelectedCircle() does).
        foreach ($chain as $ancestor) {
            if ($ancestor->parent_id === null) {
                continue; // root — already the first crumb
            }

            $crumbs[] = [
                'name' => $ancestor->locatable?->name ?? $ancestor->name,
                'url'  => route('explore', ['circle' => $ancestor->id], absolute: false),
            ];
        }

        // A location community's trail ends at its parent place.
        if ($circle->circleable_type === CommunityType::LocationCommunity->value) {
            return $crumbs;
        }

        // Sub-community (organisation, theme, campaign, course, event): the
        // explorer lists these under a type filter at their place, so the
        // trail closes with "… › Organisations" — the crumb that opens the
        // explorer at that place with that filter selected.
        $type = CommunityType::tryFrom((string) $circle->circleable_type);

        if ($type !== null) {
            // Nearest ancestor place = the circle's parent; null when that is
            // the country circle, i.e. the explorer's national level (no ?circle).
            $place = $chain->last(fn (Circle $c): bool => $c->parent_id !== null);

            $crumbs[] = [
                'name' => $type->pluralLabel(),
                'url'  => route('explore', array_filter([
                    'circle'    => $place?->id,
                    'community' => $type->name,
                ]), absolute: false),
            ];
        }

        return $crumbs;
    }
}
