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
                'available_variables' => ['contact_name', 'organisation_name', 'requester_name', 'review_url', 'expires_at'],
                'subject' => [
                    'en' => 'Action Required: Approve your organisation on Platform 2027',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ contact_name }},</p>'
                        .'<p>{{ requester_name }} has requested to add <strong>{{ organisation_name }}</strong> to '
                        .'Platform 2027. As the organisation&rsquo;s contact, we need your approval before it goes live.</p>'
                        .'<p>Review the request and choose to approve or decline it:</p>'
                        .'<p>'
                        .'<a href="{{ review_url }}" style="display:inline-block;padding:10px 20px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Review this request</a>'
                        .'</p>'
                        .'<p style="font-size:14px;color:#6b7280;">This link expires in 7 days, on {{ expires_at }}. '
                        .'If you weren&rsquo;t expecting this request, you can safely ignore this email.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.organisation_approval_admin_notice',
                'description' => 'Internal heads-up to the responsible admin when an organisation-approval request lands in their area',
                'available_variables' => ['admin_name', 'organisation_name', 'requester_name', 'review_url'],
                'subject' => [
                    'en' => 'New organisation awaiting review on Platform 2027',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ admin_name }},</p>'
                        .'<p>{{ requester_name }} has submitted <strong>{{ organisation_name }}</strong> for approval '
                        .'in an area you are responsible for. The organisation&rsquo;s contact has been asked to confirm it.</p>'
                        .'<p>You can review the request in the admin panel:</p>'
                        .'<p>'
                        .'<a href="{{ review_url }}" style="display:inline-block;padding:10px 20px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Review in admin</a>'
                        .'</p>'
                        .'<p style="font-size:14px;color:#6b7280;">Any administrator can act on this request — you are '
                        .'notified because it falls within your area.</p>',
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
            [
                'key' => 'email.organisation_member_claim_request',
                'description' => 'Sent to the organisation contact when a user claims the organisation_member internal role',
                'available_variables' => ['contact_name', 'organisation_name', 'claimer_name', 'review_url', 'expires_at'],
                'subject' => [
                    'en' => 'Action Required: Confirm a membership claim on Platform 2027',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ contact_name }},</p>'
                        .'<p><strong>{{ claimer_name }}</strong> says they are a member of staff or the board of '
                        .'<strong>{{ organisation_name }}</strong> on Platform 2027. As the organisation&rsquo;s contact, '
                        .'please confirm or reject this.</p>'
                        .'<p>'
                        .'<a href="{{ review_url }}" style="display:inline-block;padding:10px 20px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Review this claim</a>'
                        .'</p>'
                        .'<p style="font-size:14px;color:#6b7280;">This link expires in 7 days, on {{ expires_at }}. '
                        .'If you weren&rsquo;t expecting this, you can safely ignore this email.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.organisation_member_claim_approved',
                'description' => 'Sent to the claiming user when the organisation contact confirms their membership claim',
                'available_variables' => ['claimer_name', 'organisation_name'],
                'subject' => [
                    'en' => 'Your membership of {{ organisation_name }} is confirmed',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ claimer_name }},</p>'
                        .'<p>Good news — your membership of <strong>{{ organisation_name }}</strong> has been confirmed '
                        .'by the organisation&rsquo;s contact.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.organisation_member_claim_rejected',
                'description' => 'Sent to the claiming user when the organisation contact rejects their membership claim',
                'available_variables' => ['claimer_name', 'organisation_name'],
                'subject' => [
                    'en' => 'Update on your membership claim — Platform 2027',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ claimer_name }},</p>'
                        .'<p>Your claim to be a member of staff or the board of <strong>{{ organisation_name }}</strong> '
                        .'was not confirmed. You remain a member of the community, but without that role. If you believe '
                        .'this was a mistake, please get in touch with the organisation.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.theme_suggestion_approved',
                'description' => 'Sent to the user who suggested a tag when an admin approves it',
                'available_variables' => ['user_name', 'tag_name', 'review_note'],
                'subject' => [
                    'en' => 'Your suggested tag "{{ tag_name }}" was approved',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ user_name }},</p>'
                        .'<p>Good news — the tag <strong>{{ tag_name }}</strong> you suggested has been approved and is '
                        .'now available on Platform 2027.</p>',
                    'pt_BR' => '',
                ],
            ],
            [
                'key' => 'email.theme_suggestion_rejected',
                'description' => 'Sent to the user who suggested a tag when an admin rejects it',
                'available_variables' => ['user_name', 'tag_name', 'review_note'],
                'subject' => [
                    'en' => 'Update on your suggested tag "{{ tag_name }}"',
                    'pt_BR' => '',
                ],
                'body' => [
                    'en' => '<p>Hi {{ user_name }},</p>'
                        .'<p>Thank you for suggesting the tag <strong>{{ tag_name }}</strong>. After review it was not '
                        .'added at this time.</p>'
                        .'<p>{{ review_note }}</p>',
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
