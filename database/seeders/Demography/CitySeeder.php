<?php

namespace Database\Seeders\Demography;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = DB::connection('vision_summit')->table('City')->get()
            ->map(fn ($r) => [
                'id'                       => $r->ID,
                'name'                     => $r->Name,
                'code'                     => $r->Code,
                'metropolis'               => (bool) $r->Metropolis,
                'province_id'              => $r->ProvinceID ?: null,  // 0 -> NULL
                'district_municipality_id' => $r->DistrictMunicipalityID ?: null,  // 0 -> NULL
                'created_at'               => $now,
                'updated_at'               => $now,
            ])->all();

        DB::table('cities')->insert($rows);
    }
}
