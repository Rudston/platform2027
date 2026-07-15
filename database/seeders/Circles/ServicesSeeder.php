<?php

namespace Database\Seeders\Circles;

use App\Models\Circles\Service;
use App\Services\Circles\EventsService;
use App\Services\Circles\ForumService;
use App\Services\Circles\ManageLearningService;
use App\Services\Circles\ManageSocialMediaService;
use App\Services\Circles\ManageUsersService;
use App\Services\Circles\MediaService;
use App\Services\Circles\NewsService;
use App\Services\Circles\NotificationsService;
use App\Services\Circles\VotingService;
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
            ['name' => 'Forums',       'key' => 'forums',       'handler_class' => ForumService::class],
        ];

        foreach ($services as $service) {
            // The handler is the single source of truth for its UI container,
            // so read container_component off it rather than duplicating FQCNs.
            $handler = app($service['handler_class']);

            // Keyed on the unique `key` so the seeder is safe to re-run.
            Service::updateOrCreate(
                ['key' => $service['key']],
                $service + [
                    'is_active' => true,
                    'container_component' => $handler->containerComponent(),
                ],
            );
        }

        $this->command->info('Seeded '.count($services).' circle services.');
    }
}
