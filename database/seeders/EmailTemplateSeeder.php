<?php

namespace Database\Seeders;

use App\Models\Communication\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // English subject/body seeded; pt_BR intentionally left empty (falls
        // back to English via getTranslation() until translated in the admin
        // panel). Variables are referenced with the {{ variable_name }} syntax.
        $templates = [
            [
                'key' => 'email.welcome',
                'description' => 'Sent when a new user registers on the platform',
                'available_variables' => ['user_name', 'action_url'],
                'subject' => [
                    'en' => 'Welcome to Platform 2027, {{ user_name }}!',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ user_name }},</p>'
                        .'<p>Welcome to Platform 2027 — we are glad you are here. Get started by finding your first community.</p>'
                        .'<p><a href="{{ action_url }}">Explore communities</a></p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.circle_invitation',
                'description' => 'Sent when a user is invited to join a circle',
                'available_variables' => ['user_name', 'circle_name', 'action_url'],
                'subject' => [
                    'en' => 'You have been invited to join {{ circle_name }}',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ user_name }},</p>'
                        .'<p>You have been invited to join <strong>{{ circle_name }}</strong> on Platform 2027.</p>'
                        .'<p><a href="{{ action_url }}">View the invitation</a></p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.password_reset',
                'description' => 'Sent when a user requests a password reset',
                'available_variables' => ['user_name', 'action_url'],
                'subject' => [
                    'en' => 'Reset your Platform 2027 password',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ user_name }},</p>'
                        .'<p>We received a request to reset your password. Click the link below to choose a new one. '
                        .'If you did not make this request, you can safely ignore this email.</p>'
                        .'<p><a href="{{ action_url }}">Reset password</a></p>',
                    'pt_BR' => '',
                ],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                [
                    'description' => $template['description'],
                    'available_variables' => $template['available_variables'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'is_html' => true,
                    'is_active' => true,
                ],
            );
        }

        $this->command->info(sprintf('Seeded %d email templates.', count($templates)));
    }
}
