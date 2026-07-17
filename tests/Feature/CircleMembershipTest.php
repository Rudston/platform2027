<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Livewire\Communities\CommunityPage;
use App\Livewire\Explore\CommunityCard;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Communities\Campaign;
use App\Models\Communities\OrganisationCommunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Circle membership: join/leave, the per-type concurrent cap, the swap rule,
 * internal-role validation, and the "Enter"/"Visit" card label.
 *
 * Campaign is used as the circleable for most cases because it does NOT
 * implement HasDefaultServices, so creating its circle skips service
 * attachment (no services tables needed).
 */
class CircleMembershipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();
        (include database_path('migrations/2026_07_16_000001_create_circle_memberships_table.php'))->up();

        Schema::create('circles', function ($table): void {
            $table->id();
            $table->string('circleable_type')->nullable();
            $table->unsignedBigInteger('circleable_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('path')->nullable();
            $table->string('name')->nullable();
            $table->json('description')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_test')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['campaigns', 'organisation_communities'] as $t) {
            Schema::create($t, function ($table): void {
                $table->id();
                $table->unsignedBigInteger('organisation_id')->nullable();
                $table->string('name')->nullable();
                $table->text('description')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // Empty services table so an OrganisationCommunity circle can be created
        // (its booted() hook queries services; attaches none from an empty set).
        Schema::create('services', function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');
            $table->string('handler_class')->nullable();
            $table->string('container_component')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function makeCampaignCircle(): Circle
    {
        $c = Campaign::create(['name' => 'C']);

        return Circle::create([
            'circleable_type' => CommunityType::Campaign->value,
            'circleable_id' => $c->id,
            'name' => 'Campaign',
        ]);
    }

    private function makeOrgCircle(): Circle
    {
        $o = OrganisationCommunity::create(['name' => 'O']);

        return Circle::create([
            'circleable_type' => CommunityType::Organisation->value,
            'circleable_id' => $o->id,
            'name' => 'Org',
        ]);
    }

    public function test_joining_creates_an_active_membership(): void
    {
        $circle = $this->makeCampaignCircle();
        $user = User::factory()->create();

        $circle->joinAsMember($user);

        $this->assertNotNull($circle->activeMembership($user));
        $this->assertNull($circle->activeMembership($user)->left_at);
    }

    public function test_joining_is_idempotent(): void
    {
        $circle = $this->makeCampaignCircle();
        $user = User::factory()->create();

        $circle->joinAsMember($user);
        $circle->joinAsMember($user);

        $this->assertSame(1, CircleMembership::where('circle_id', $circle->id)->whereNull('left_at')->count());
    }

    public function test_leaving_closes_the_active_membership(): void
    {
        $circle = $this->makeCampaignCircle();
        $user = User::factory()->create();
        $circle->joinAsMember($user);

        $circle->leave($user);

        $this->assertNull($circle->activeMembership($user));
        $this->assertSame(1, CircleMembership::where('circle_id', $circle->id)->whereNotNull('left_at')->count());
    }

    public function test_join_is_blocked_at_the_cap_without_a_swappable_membership(): void
    {
        $user = User::factory()->create();
        // Cap for Campaign is 2 — fill it with recent memberships.
        $this->makeCampaignCircle()->joinAsMember($user);
        $this->makeCampaignCircle()->joinAsMember($user);

        $third = $this->makeCampaignCircle();
        $state = $third->canUserJoin($user);

        $this->assertFalse($state['allowed']);
        $this->assertNotNull($state['available_at']);   // earliest eligible date
        $this->assertTrue($state['swappable']->isEmpty());
    }

    public function test_join_is_allowed_via_swap_when_a_membership_is_old_enough(): void
    {
        $user = User::factory()->create();
        $a = $this->makeCampaignCircle();
        $b = $this->makeCampaignCircle();
        $a->joinAsMember($user);
        $b->joinAsMember($user);

        // Age membership A past the 3-month hold.
        $a->activeMembership($user)->update(['joined_at' => now()->subMonths(4)]);

        $third = $this->makeCampaignCircle();
        $state = $third->canUserJoin($user);

        $this->assertTrue($state['allowed']);
        $this->assertCount(1, $state['swappable']);

        // Joining via swap closes the dropped membership.
        $third->joinAsMember($user, dropMembership: $state['swappable']->first());

        $this->assertNull($a->activeMembership($user));           // dropped
        $this->assertNotNull($third->activeMembership($user));    // joined
        $this->assertSame(2, CircleMembership::where('user_id', $user->id)->whereNull('left_at')->count());
    }

    public function test_global_admins_bypass_the_cap(): void
    {
        $user = User::factory()->create();
        $this->makeCampaignCircle()->joinAsMember($user);
        $this->makeCampaignCircle()->joinAsMember($user);

        // Grant the global admin role.
        $roleId = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id, 'circle_id' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Fresh instance so Spatie re-reads roles (a real request always has one).
        $this->assertTrue($this->makeCampaignCircle()->canUserJoin($user->fresh())['allowed']);
    }

    public function test_join_rejects_an_internal_role_not_allowed_by_the_type(): void
    {
        $circle = $this->makeCampaignCircle();   // Campaign allows no internal roles
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $circle->joinAsMember($user, internalRole: 'organisation_member');
    }

    public function test_organisation_community_accepts_its_internal_role(): void
    {
        $circle = $this->makeOrgCircle();
        $user = User::factory()->create();

        $membership = $circle->joinAsMember($user, internalRole: 'organisation_member');

        $this->assertSame('organisation_member', $membership->internal_role);
    }

    public function test_admin_member_can_add_self_as_circle_admin(): void
    {
        $circle = $this->makeCampaignCircle();
        // circle_admin must exist for the grant (seeded in the real app).
        DB::table('roles')->insert(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        $admin = User::factory()->create();
        $roleId = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $admin->id, 'circle_id' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin->fresh());

        // Not joined yet → button not offered.
        $page = new CommunityPage;
        $page->circle = $circle;
        $this->assertFalse($page->canAddSelfAsCircleAdmin());

        // After joining → offered, and the action grants circle_admin here.
        $circle->joinAsMember($admin->fresh());
        $page = new CommunityPage;
        $page->circle = $circle;
        $this->assertTrue($page->canAddSelfAsCircleAdmin());

        $page->addSelfAsCircleAdmin();
        $this->assertTrue($circle->isAdministeredBy($admin->fresh()));

        // Already an admin here → no longer offered; re-running is a no-op.
        $page = new CommunityPage;
        $page->circle = $circle;
        $this->assertFalse($page->canAddSelfAsCircleAdmin());
        $page->addSelfAsCircleAdmin();
        $this->assertCount(1, $circle->administrators());
    }

    public function test_remove_self_as_circle_admin_blocked_when_sole_admin(): void
    {
        DB::table('roles')->insert(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        $circle = $this->makeCampaignCircle();
        $admin = User::factory()->create();
        $circle->addAdministrator($admin);
        $this->assertTrue($circle->isAdministeredBy($admin->fresh()));

        $this->actingAs($admin->fresh());
        $page = new CommunityPage;
        $page->circle = $circle;

        // Sole admin → self-removal is a no-op (guard); still the admin.
        $page->removeSelfAsCircleAdmin();
        $this->assertTrue($circle->isAdministeredBy($admin->fresh()));
        $this->assertCount(1, $circle->administrators());
    }

    public function test_remove_self_as_circle_admin_when_another_exists(): void
    {
        DB::table('roles')->insert(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        $circle = $this->makeCampaignCircle();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $circle->addAdministrator($a);
        $circle->addAdministrator($b);
        $this->assertCount(2, $circle->administrators());

        $this->actingAs($a->fresh());
        $page = new CommunityPage;
        $page->circle = $circle;

        $page->removeSelfAsCircleAdmin();

        $this->assertFalse($circle->isAdministeredBy($a->fresh())); // dropped
        $this->assertTrue($circle->isAdministeredBy($b->fresh()));  // other remains
        $this->assertCount(1, $circle->administrators());
    }

    public function test_non_admin_member_cannot_add_self_as_circle_admin(): void
    {
        $circle = $this->makeCampaignCircle();
        $user = User::factory()->create(); // regular member, no global role
        $circle->joinAsMember($user);

        $this->actingAs($user->fresh());
        $page = new CommunityPage;
        $page->circle = $circle;

        $this->assertFalse($page->canAddSelfAsCircleAdmin());
        $page->addSelfAsCircleAdmin();
        $this->assertFalse($circle->isAdministeredBy($user->fresh()));
    }

    public function test_member_count_reflects_active_memberships(): void
    {
        $circle = $this->makeCampaignCircle();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $circle->joinAsMember($a);
        $circle->joinAsMember($b);

        $page = new CommunityPage;
        $page->circle = $circle;
        $this->assertSame(2, $page->memberCount());

        $circle->leave($a);
        $this->assertSame(1, $page->memberCount());
    }

    public function test_community_card_label_reflects_membership(): void
    {
        $circle = $this->makeCampaignCircle();

        Livewire::test(CommunityCard::class, ['circle' => $circle, 'isMember' => true])->assertSee('Enter');
        Livewire::test(CommunityCard::class, ['circle' => $circle, 'isMember' => false])->assertSee('Visit');
    }

    public function test_community_card_shows_the_member_count_passed_in(): void
    {
        $circle = $this->makeCampaignCircle();

        // Count is passed in (batch-loaded by the list), never queried per-card.
        Livewire::test(CommunityCard::class, ['circle' => $circle, 'memberCount' => 5])
            ->assertSee('5 members');
    }
}
