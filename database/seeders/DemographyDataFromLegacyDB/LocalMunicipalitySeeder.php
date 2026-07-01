<?php

namespace Database\Seeders\DemographyDataFromLegacyDB;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalMunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Source `LocationID` is intentionally dropped (Location is excluded).
        $rows = DB::connection('vision_summit')->table('LocalMunicipality')->get()
            ->map(fn ($r) => [
                'id'                       => $r->ID,
                'name'                     => $r->Name,
                'code'                     => $r->Code,
                'seat'                     => $r->Seat,
                'population'               => $r->Population,   // int in source
                'district_municipality_id' => $r->DistrictMunicipalityID ?: null,  // 0 -> NULL
                'district'                 => $r->District,
                'population_str'           => $r->PopulationStr,
                'province_id'              => $r->ProvinceID ?: null,  // 0 -> NULL
                'created_at'               => $now,
                'updated_at'               => $now,
            ])->all();

        DB::table('local_municipalities')->insert($rows);
    }
}
