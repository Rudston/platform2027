<?php

namespace Database\Seeders\DemographyDataFromLegacyDB;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoordinateDataSeeder extends Seeder
{
    public function run(): void
    {
        // ~12.7k rows: stream from the source in chunks to keep memory flat.
        DB::connection('vision_summit')->table('CoordinateData')->orderBy('ID')
            ->chunk(1000, function ($chunk) {
                $now = now();

                $rows = $chunk->map(fn ($r) => [
                    'id'            => $r->ID,
                    'city'          => $r->City,          // plain string, not a FK
                    'accent_city'   => $r->AccentCity,
                    'province_name' => $r->ProvinceName,
                    'latitude'      => $r->Latitude,
                    'longitude'     => $r->Longitude,
                    'province_id'   => $r->ProvinceID ?: null,  // 0 -> NULL (loose ref)
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ])->all();

                DB::table('coordinate_data')->insert($rows);
            });
    }
}
