<?php

namespace Database\Seeders\Demography;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UrbanPlaceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Source `LocationID` is intentionally dropped (Location is excluded).
        $rows = DB::connection('vision_summit')->table('UrbanPlace')->get()
            ->map(fn ($r) => [
                'id'         => $r->ID,
                'name'       => $r->Name,
                'code'       => $r->Code,
                'population' => $r->Population,   // varchar in source
                'language'   => $r->Language,
                'city_id'    => $r->CityID ?: null,  // 0 -> NULL
                'city_str'   => $r->CityStr,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        DB::table('urban_places')->insert($rows);
    }
}
