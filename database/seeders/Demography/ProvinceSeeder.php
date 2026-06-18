<?php

namespace Database\Seeders\Demography;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = DB::connection('vision_summit')->table('Province')->get()
            ->map(fn ($r) => [
                'id'         => $r->ID,
                'name'       => $r->Name,
                'code'       => $r->Code,
                'capital'    => $r->Capital,
                'article'    => $r->Article,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        DB::table('provinces')->insert($rows);
    }
}
