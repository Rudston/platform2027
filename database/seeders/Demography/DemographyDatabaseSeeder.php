<?php

namespace Database\Seeders\Demography;

use Illuminate\Database\Seeder;

/**
 * Imports the geography/demography subset from the `vision_summit` database
 * into `platform2027`. Run explicitly:
 *
 *   php artisan db:seed --class="Database\Seeders\Demography\DemographyDatabaseSeeder"
 *
 * Assumes freshly-migrated, empty target tables (original IDs are preserved,
 * so re-running over existing data would collide on primary keys).
 */
class DemographyDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,                  // no dependencies
            DistrictMunicipalitySeeder::class,      // -> provinces (main_city_id deferred)
            LocalMunicipalitySeeder::class,         // -> provinces, district_municipalities
            CitySeeder::class,                       // -> provinces, district_municipalities
            UrbanPlaceSeeder::class,                 // -> cities
            CoordinateDataSeeder::class,             // standalone
            DistrictMunicipalityMainCitySeeder::class, // 2nd pass: fill main_city_id
        ]);
    }
}
