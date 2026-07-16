<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\RequestType;
use App\Livewire\Communities\CommunityPage;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Communication\Request;
use App\Models\Communities\OrganisationCommunity;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Organisation-member-claim flow: a non-creator claiming the
 * 'organisation_member' internal role must be confirmed by the org contact via
 * an external token request. RequestType cast + CircleMembership approval gate.
 */
class OrganisationMemberClaimTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();
        $this->buildCirclesTable();
        (include database_path('migrations/2026_07_16_000001_create_circle_memberships_table.php'))->up();
        (include database_path('migrations/2026_07_07_000003_create_requests_table.php'))->up();
        (include database_path('migrations/2026_07_14_000001_add_responsible_admin_id_to_requests_table.php'))->up();
        (include database_path('migrations/2026_07_06_000001_create_email_templates_table.php'))->up();

        Schema::create('organisation_communities', function ($t): void {
            $t->id();
            $t->unsignedBigInteger('organisation_id')->nullable();
            $t->string('name')->nullable();
            $t->text('description')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('organisations', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->text('description')->nullable();
            $t->string('website')->nullable();
            $t->string('contact_person')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('contact_job_title')->nullable();
            $t->timestamps();
        });

        // Empty services table so the org circle's booted() hook can run.
        Schema::create('services', function ($t): void {
            $t->id();
            $t->string('key')->unique();
            $t->json('name');
            $t->string('handler_class')->nullable();
            $t->string('container_component')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        $this->seed(EmailTemplateSeeder::class);
    }

    private function buildCirclesTable(): void
    {
        Schema::create('circles', function ($t): void {
            $t->id();
            $t->string('circleable_type')->nullable();
            $t->unsignedBigInteger('circleable_id')->nullable();
            $t->string('locatable_type')->nullable();
            $t->unsignedBigInteger('locatable_id')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->unsignedTinyInteger('depth')->default(0);
            $t->string('path')->nullable();
            $t->string('name')->nullable();
            $t->json('description')->nullable();
            $t->string('status')->default('active');
            $t->boolean('is_test')->default(false);
            $t->softDeletes();
            $t->timestamps();
        });
    }

    private function makeOrgCircle(string $contactEmail = 'contact@acme.test'): Circle
    {
        $org = Organisation::create([
            'name' => 'Acme',
            'contact_person' => 'Pat Contact',
            'contact_email' => $contactEmail,
        ]);
        $oc = OrganisationCommunity::create(['name' => 'Acme', 'organisation_id' => $org->id]);

        return Circle::create([
            'circleable_type' => CommunityType::Organisation->value,
            'circleable_id' => $oc->id,
            'name' => 'Acme',
        ]);
    }

    public function test_request_type_is_cast_to_the_enum(): void
    {
        $circle = $this->makeOrgCircle();
        $membership = $circle->joinAsMember(User::factory()->create()); // no role

        $request = Request::createForMemberClaim(
            User::factory()->create(),
            $circle,
            $membership,
            'contact@acme.test',
        );

        $this->assertInstanceOf(RequestType::class, $request->fresh()->type);
        $this->assertSame(RequestType::OrganisationMemberClaim, $request->fresh()->type);
    }

    public function test_non_creator_claim_sets_pending_and_opens_a_request(): void
    {
        $circle = $this->makeOrgCircle('pat@acme.test');
        $user = User::factory()->create();

        $membership = $circle->joinAsMember($user, internalRole: 'organisation_member');

        // Member immediately, but the role is gated pending.
        $this->assertSame('organisation_member', $membership->internal_role);
        $this->assertSame('pending', $membership->fresh()->metadata['internal_role_approved']);
        $this->assertFalse($membership->fresh()->hasApprovedInternalRole());

        // A claim request was opened to the org contact.
        $request = Request::where('type', RequestType::OrganisationMemberClaim->value)->first();
        $this->assertNotNull($request);
        $this->assertSame($user->id, $request->requester_id);
        $this->assertSame('pat@acme.test', $request->respondent_email);
        $this->assertSame($membership->id, (int) $request->requestable_id);
    }

    public function test_creator_grant_does_not_trigger_a_claim(): void
    {
        $circle = $this->makeOrgCircle();
        $creator = User::factory()->create();

        // The trusted approval-hook grant (skipChecks) — role is approved
        // outright, and it must NOT open a claim request.
        $membership = $circle->joinAsMember($creator, internalRole: 'organisation_member', skipChecks: true);

        $this->assertSame('approved', $membership->fresh()->metadata['internal_role_approved']);
        $this->assertTrue($membership->fresh()->hasApprovedInternalRole());
        $this->assertSame(0, Request::where('type', RequestType::OrganisationMemberClaim->value)->count());
    }

    public function test_approving_the_claim_marks_the_membership_approved(): void
    {
        $circle = $this->makeOrgCircle();
        $user = User::factory()->create();
        $membership = $circle->joinAsMember($user, internalRole: 'organisation_member');
        $token = Request::where('type', RequestType::OrganisationMemberClaim->value)->value('token');

        $this->post(route('requests.confirm.approve', $token))->assertOk();

        $this->assertSame('approved', $membership->fresh()->metadata['internal_role_approved']);
        $this->assertTrue($membership->fresh()->hasApprovedInternalRole());
        $this->assertSame('approved', Request::where('token', $token)->value('status'));
    }

    public function test_rejecting_the_claim_keeps_the_role_but_marks_it_rejected(): void
    {
        $circle = $this->makeOrgCircle();
        $user = User::factory()->create();
        $membership = $circle->joinAsMember($user, internalRole: 'organisation_member');
        $token = Request::where('type', RequestType::OrganisationMemberClaim->value)->value('token');

        $this->post(route('requests.confirm.deny', $token), ['response_note' => 'Not on our list'])->assertOk();

        $fresh = $membership->fresh();
        $this->assertSame('rejected', $fresh->metadata['internal_role_approved']);
        $this->assertSame('organisation_member', $fresh->internal_role); // kept for audit
        $this->assertFalse($fresh->hasApprovedInternalRole());
    }

    public function test_community_page_lists_only_approved_org_members(): void
    {
        $circle = $this->makeOrgCircle();

        $approved = User::factory()->create(['name' => 'Approved One']);
        $pending = User::factory()->create(['name' => 'Pending One']);

        $circle->joinAsMember($pending, internalRole: 'organisation_member'); // stays pending
        $approvedMembership = $circle->joinAsMember($approved, internalRole: 'organisation_member');
        $approvedMembership->update(['metadata' => ['internal_role_approved' => 'approved']]);

        $page = new CommunityPage;
        $page->circle = $circle;
        $names = $page->organisationMembers()->map(fn ($m) => $m->user->name)->all();

        $this->assertContains('Approved One', $names);
        $this->assertNotContains('Pending One', $names);
    }

    public function test_has_approved_internal_role_gate(): void
    {
        $pending = new CircleMembership(['internal_role' => 'organisation_member', 'metadata' => ['internal_role_approved' => 'pending']]);
        $approved = new CircleMembership(['internal_role' => 'organisation_member', 'metadata' => ['internal_role_approved' => 'approved']]);
        $rejected = new CircleMembership(['internal_role' => 'organisation_member', 'metadata' => ['internal_role_approved' => 'rejected']]);
        $noRole = new CircleMembership(['internal_role' => null, 'metadata' => ['internal_role_approved' => 'approved']]);

        $this->assertFalse($pending->hasApprovedInternalRole());
        $this->assertTrue($approved->hasApprovedInternalRole());
        $this->assertFalse($rejected->hasApprovedInternalRole());
        $this->assertFalse($noRole->hasApprovedInternalRole());
    }
}
