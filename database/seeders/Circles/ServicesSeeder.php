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
            ['name' => 'Media',        'key' => 'media',        'handler_class' => MediaService::class],
            ['name' => 'Notifications', 'key' => 'notifications',       'handler_class' => NotificationsService::class],
            ['name' => 'News',  'key' => 'news',  'handler_class' => NewsService::class],
            ['name' => 'Manage Users',  'key' => 'manage_users',        'handler_class' => ManageUsersService::class],
            ['name' => 'Events',       'key' => 'events',       'handler_class' => EventsService::class],
            ['name' => 'Voting',       'key' => 'voting',       'handler_class' => VotingService::class],
            ['name' => 'Learning',     'key' => 'learning',     'handler_class' => ManageLearningService::class],
            ['name' => 'Social Media', 'key' => 'social_media', 'handler_class' => ManageSocialMediaService::class],
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
