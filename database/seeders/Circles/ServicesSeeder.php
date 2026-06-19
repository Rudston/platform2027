<?php

namespace Database\Seeders\Circles;

use App\Models\Circles\Service;
use App\Services\Circles\ManageEventsService;
use App\Services\Circles\ManageInteractionService;
use App\Services\Circles\ManageLearningService;
use App\Services\Circles\ManageMediaService;
use App\Services\Circles\ManageSocialMediaService;
use App\Services\Circles\ManageUsersService;
use App\Services\Circles\ManageVotingService;
use App\Services\Circles\NotificationsService;
use App\Services\Circles\StoreAssetsService;
use Illuminate\Database\Seeder;

class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Store Assets',        'key' => 'store_assets',        'handler_class' => StoreAssetsService::class],
            ['name' => 'Notifications',       'key' => 'notifications',       'handler_class' => NotificationsService::class],
            ['name' => 'Manage Interaction',  'key' => 'manage_interaction',  'handler_class' => ManageInteractionService::class],
            ['name' => 'Manage Media',        'key' => 'manage_media',        'handler_class' => ManageMediaService::class],
            ['name' => 'Manage Users',        'key' => 'manage_users',        'handler_class' => ManageUsersService::class],
            ['name' => 'Manage Events',       'key' => 'manage_events',       'handler_class' => ManageEventsService::class],
            ['name' => 'Manage Voting',       'key' => 'manage_voting',       'handler_class' => ManageVotingService::class],
            ['name' => 'Manage Learning',     'key' => 'manage_learning',     'handler_class' => ManageLearningService::class],
            ['name' => 'Manage Social Media', 'key' => 'manage_social_media', 'handler_class' => ManageSocialMediaService::class],
        ];

        foreach ($services as $service) {
            // Keyed on the unique `key` so the seeder is safe to re-run.
            Service::updateOrCreate(
                ['key' => $service['key']],
                $service + ['is_active' => true],
            );
        }

        $this->command->info('Seeded '.count($services).' circle services.');
    }
}
