<?php

namespace Database\Seeders\DemographyDataFromLegacyDB;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictMunicipalityMainCitySeeder extends Seeder
{
    /**
     * Second pass: fill district_municipalities.main_city_id now that all
     * cities exist. The source stores this directly as
     * DistrictMunicipality.MainCityID (there is no is_main flag on City).
     */
    public function run(): void
    {
        // Fast lookup of valid city IDs so a stale MainCityID can't violate
        // the FK constraint.
        $cityIds = DB::table('cities')->pluck('id')->flip();

        foreach (DB::connection('vision_summit')->table('DistrictMunicipality')->get() as $r) {
            $mainCityId = $r->MainCityID ?: null;  // 0 -> NULL

            if ($mainCityId !== null && $cityIds->has($mainCityId)) {
                DB::table('district_municipalities')
                    ->where('id', $r->ID)
                    ->update(['main_city_id' => $mainCityId]);
            }
        }
    }
}
