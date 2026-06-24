<?php

namespace Database\Seeders\Circles;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Demography\City;
use App\Models\Demography\Country;
use App\Models\Demography\DistrictMunicipality;
use App\Models\Demography\LocalMunicipality;
use App\Models\Demography\Province;
use App\Services\Circles\CircleCreationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationCommunitiesSeeder extends Seeder
{
    public function __construct(
        protected CircleCreationService $circleCreationService
    ) {
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Step 1: Country level (root — no parent)
            $country = Country::where('id', 191)->first();
            $countryCircle = $this->circleCreationService->create(
                type:          CommunityType::LocationCommunity,
                data:          ['name' => $country->circleName(), 'description' => $country->circleDescription()],
                locatableType: LocatableType::Country,
                locatableId:   $country->id,
            );

            $this->command->info("Created: {$countryCircle->name}");

            // Step 2: Provinces — children of country circle
            Province::all()->each(function (Province $province) use ($countryCircle) {

                $provinceCircle = $this->circleCreationService->create(
                    type:          CommunityType::LocationCommunity,
                    data:          ['name' => $province->circleName(), 'description' => $province->circleDescription()],
                    parentCircle:  $countryCircle,
                    locatableType: LocatableType::Province,
                    locatableId:   $province->id,
                );

                $this->command->info("  Created: {$provinceCircle->name}");

                // Step 3a: District municipalities — children of province circle
                $province->districtMunicipalities->each(
                    function (DistrictMunicipality $dm) use ($provinceCircle) {

                        $dmCircle = $this->circleCreationService->create(
                            type:          CommunityType::LocationCommunity,
                            data:          ['name' => $dm->circleName(), 'description' => $dm->circleDescription()],
                            parentCircle:  $provinceCircle,
                            locatableType: LocatableType::DistrictMunicipality,
                            locatableId:   $dm->id,
                        );

                        $this->command->info("    Created: {$dmCircle->name}");

                        // Step 4: Local municipalities — children of DM circle
                        $dm->localMunicipalities->each(
                            function (LocalMunicipality $lm) use ($dmCircle) {

                                $lmCircle = $this->circleCreationService->create(
                                    type:          CommunityType::LocationCommunity,
                                    data:          ['name' => $lm->circleName(), 'description' => $lm->circleDescription()],
                                    parentCircle:  $dmCircle,
                                    locatableType: LocatableType::LocalMunicipality,
                                    locatableId:   $lm->id,
                                );

                                $this->command->info("      Created: {$lmCircle->name}");
                            }
                        );
                    }
                );

                // Step 3b: Cities — also children of province circle
                $province->cities->each(function (City $city) use ($provinceCircle) {

                    $cityCircle = $this->circleCreationService->create(
                        type:          CommunityType::LocationCommunity,
                        data:          ['name' => $city->circleName(), 'description' => $city->circleDescription()],
                        parentCircle:  $provinceCircle,
                        locatableType: LocatableType::City,
                        locatableId:   $city->id,
                    );

                    $this->command->info("    Created: {$cityCircle->name}");
                });
            });

            $this->command->info("\nLocation communities seeded successfully.");
        });
    }
}
