<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        // English content seeded; pt_BR intentionally left empty (falls back to
        // English via ContentBlock::get() until translated in the admin panel).
        $blocks = [
            [
                'key' => 'explore.welcome_banner',
                'description' => 'Introductory text shown at the top of the Explore page',
                'content' => [
                    'en' => '<p>Welcome to Platform 2027 — explore the communities organising across South Africa and find where you belong.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'explore.column_browser_hint',
                'description' => 'Helper text shown above the geographic column browser',
                'content' => [
                    'en' => '<p>Drill down by province, municipality and area to discover the communities nearest you.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'community.join_instructions',
                'description' => 'Instructions shown on a community page for joining',
                'content' => [
                    'en' => '<p>Join this community to take part in its discussions, events and collective action.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'onboarding.new_user_welcome',
                'description' => 'Welcome message shown to a newly registered user',
                'content' => [
                    'en' => '<p>Welcome aboard! Complete your profile and find your first community to get started.</p>',
                    'pt_BR' => '',
                ],
            ],
        ];

        foreach ($blocks as $block) {
            ContentBlock::updateOrCreate(
                ['key' => $block['key']],
                [
                    'description' => $block['description'],
                    'content' => $block['content'],
                    'is_html' => true,
                ],
            );
        }

        $this->command->info(sprintf('Seeded %d content blocks.', count($blocks)));
    }
}
