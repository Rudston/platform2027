<?php

namespace Tests\Services;

use App\Mail\TemplateMailable;
use App\Models\User;
use App\Services\Communication\EmailServiceHandler;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailServiceHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The full migration set cannot run on the sqlite test database: a
        // demography backfill migration references a `countries` table that no
        // migration creates. Build only the tables this test needs.
        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_07_06_000001_create_email_templates_table.php'))->up();
    }

    public function test_it_sends_the_welcome_email_to_the_user(): void
    {
        Mail::fake();

        // Seed the initial templates (includes the "email.welcome" template).
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->create([
            'name' => 'Rudston',
            'email' => 'rudston@mobilize.org.za',
        ]);

        (new EmailServiceHandler())->sendTemplate(
            'email.welcome',
            $user->email,
            [
                'user_name' => $user->name,
                'action_url' => url('/explore'),
            ],
        );

        // The welcome mailable was dispatched to this user, with the
        // {{ user_name }} placeholder substituted into the subject.
        Mail::assertSent(TemplateMailable::class, function (TemplateMailable $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && str_contains($mail->subject, $user->name)
                && ! str_contains($mail->subject, '{{');
        });
    }
}
