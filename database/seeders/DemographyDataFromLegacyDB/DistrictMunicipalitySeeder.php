<?php

namespace Database\Seeders\DemographyDataFromLegacyDB;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictMunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // main_city_id is left unset here (filled in the second pass by
        // DistrictMunicipalityMainCitySeeder, once cities exist).
        $rows = DB::connection('vision_summit')->table('DistrictMunicipality')->get()
            ->map(fn ($r) => [
                'id'           => $r->ID,
                'name'         => $r->Name,
                'code'         => $r->Code,
                'seat'         => $r->Seat,
                'population'   => $r->Population,    // varchar in source
                'province_str' => $r->ProvinceStr,
                'province_id'  => $r->ProvinceID ?: null,  // 0 -> NULL
                'created_at'   => $now,
                'updated_at'   => $now,
            ])->all();

        DB::table('district_municipalities')->insert($rows);
    }
}
