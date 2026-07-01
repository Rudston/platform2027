<?php

/**
 * Note: around 5692 coordinate_data records have matching main_place records:
 * I have these tables:
 *
 * platform2027.coordinate_data with a field 'accent_city'
 *
 * platform2027.main_places with a field 'name'.
 *
 * Is there a quick mysql query that will give the count of how many
 * records in platform2027.coordinate_data have 'accent_city'
 * like('%...%') platform2027.main_places 'name'?
 *
 * SELECT COUNT(*) AS matching_coordinate_data_count
 * FROM platform2027.coordinate_data cd
 * WHERE cd.accent_city IS NOT NULL
 * AND EXISTS (
 * SELECT 1
 * FROM platform2027.main_places mp
 * WHERE mp.name IS NOT NULL
 * AND mp.name <> ''
 * AND cd.accent_city LIKE CONCAT('%', mp.name, '%')
 * );
 */

namespace App\Models\Demography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordinateData extends Model
{
    // Explicit table name: Laravel would not pluralise "CoordinateData" to
    // "coordinate_data" reliably.
    protected $table = 'coordinate_data';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    // Loose legacy reference (province_id has no FK constraint at the DB level).
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function getMainPlace(): ?MainPlace
    {
        return MainPlace::where('name', 'like', '%' . $this->accent_city . '%')->first();
    }

    /**
     * Nearest coordinate to the given point by squared Euclidean distance.
     *
     * Primary query pre-filters with a ±0.5° (~55km) bounding box, then sorts
     * by squared distance (no SQRT needed — ordering is identical). If the box
     * yields nothing (sparse areas), falls back to a full-table nearest scan.
     * Returns null on an empty table / no rows — never throws.
     */
    public static function nearest(float $latitude, float $longitude): ?static
    {
        $distance = '((latitude - ?) * (latitude - ?)) + ((longitude - ?) * (longitude - ?))';
        $distanceBindings = [$latitude, $latitude, $longitude, $longitude];

        // Primary: bounding-box pre-filter + squared-distance sort.
        $nearest = static::query()
            ->selectRaw("*, {$distance} AS dist", $distanceBindings)
            ->whereRaw('latitude BETWEEN ? - 0.5 AND ? + 0.5', [$latitude, $latitude])
            ->whereRaw('longitude BETWEEN ? - 0.5 AND ? + 0.5', [$longitude, $longitude])
            ->orderByRaw('dist ASC')
            ->limit(1)
            ->first();

        if ($nearest) {
            return $nearest;
        }

        // Fallback: no bounding box (e.g. a user in a sparse region).
        return static::query()
            ->selectRaw("*, {$distance} AS dist", $distanceBindings)
            ->orderByRaw('dist ASC')
            ->limit(1)
            ->first();
    }
}
