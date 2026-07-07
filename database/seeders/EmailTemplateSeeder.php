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
            [
                'key' => 'email.organisation_approval_request',
                'description' => 'Sent to the organisation contact requesting approval to add the organisation to the platform',
                'available_variables' => ['contact_name', 'organisation_name', 'requester_name', 'approve_url', 'deny_url', 'expires_at'],
                'subject' => [
                    'en' => 'Action Required: Approve your organisation on Platform 2027',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ contact_name }},</p>'
                        .'<p>{{ requester_name }} has requested to add <strong>{{ organisation_name }}</strong> to '
                        .'Platform 2027. As the organisation&rsquo;s contact, we need your approval before it goes live.</p>'
                        .'<p>Please review and respond:</p>'
                        .'<p>'
                        .'<a href="{{ approve_url }}" style="display:inline-block;padding:10px 20px;margin-right:8px;background-color:#16a34a;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Approve</a>'
                        .'<a href="{{ deny_url }}" style="display:inline-block;padding:10px 20px;background-color:#dc2626;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Deny</a>'
                        .'</p>'
                        .'<p style="font-size:14px;color:#6b7280;">This link expires in 7 days, on {{ expires_at }}. '
                        .'If you weren&rsquo;t expecting this request, you can safely ignore this email.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.organisation_approval_confirmed',
                'description' => 'Sent to the requester when the organisation contact approves the request',
                'available_variables' => ['requester_name', 'organisation_name', 'community_url'],
                'subject' => [
                    'en' => 'Your organisation has been approved on Platform 2027',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ requester_name }},</p>'
                        .'<p>Great news — <strong>{{ organisation_name }}</strong> has been approved and is now live '
                        .'on Platform 2027.</p>'
                        .'<p>You can visit and start building the community here:</p>'
                        .'<p><a href="{{ community_url }}">Visit {{ organisation_name }}</a></p>'
                        .'<p>Welcome aboard — we&rsquo;re glad to have you.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.organisation_approval_denied',
                'description' => 'Sent to the requester when the organisation contact denies the request',
                'available_variables' => ['requester_name', 'organisation_name'],
                'subject' => [
                    'en' => 'Update on your organisation request — Platform 2027',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ requester_name }},</p>'
                        .'<p>Thank you for your request to add <strong>{{ organisation_name }}</strong> to Platform 2027.</p>'
                        .'<p>After review, this request was not approved at this time. If you believe this was a '
                        .'mistake, or you&rsquo;d like to discuss it further, please get in touch with us.</p>'
                        .'<p>We appreciate your understanding.</p>',
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
