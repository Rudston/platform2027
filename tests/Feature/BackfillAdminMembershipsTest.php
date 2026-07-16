<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Models\Circles\CircleMembership;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * circles:backfill-admin-memberships — every existing circle_admin gets an
 * active membership; organisation-community admins get the 'organisation_member'
 * internal role. Idempotent, adds-only.
 */
class BackfillAdminMembershipsTest extends TestCase
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
            $table->string('path')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function makeCircle(string $circleableType): int
    {
        return DB::table('circles')->insertGetId([
            'circleable_type' => $circleableType,
            'circleable_id' => 1,
        ]);
    }

    private function grantCircleAdmin(int $userId, int $circleId): void
    {
        $roleId = DB::table('roles')->where('name', 'circle_admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
            'circle_id' => $circleId,
        ]);
    }

    public function test_it_backfills_admin_memberships_with_the_right_internal_role(): void
    {
        $orgCircle = $this->makeCircle(CommunityType::Organisation->value);
        $campaignCircle = $this->makeCircle(CommunityType::Campaign->value);

        $orgAdmin = User::factory()->create();
        $campaignAdmin = User::factory()->create();
        $this->grantCircleAdmin($orgAdmin->id, $orgCircle);
        $this->grantCircleAdmin($campaignAdmin->id, $campaignCircle);

        // A circle_admin who already has an active membership must not be duplicated.
        $existing = User::factory()->create();
        $this->grantCircleAdmin($existing->id, $campaignCircle);
        CircleMembership::create([
            'circle_id' => $campaignCircle,
            'user_id' => $existing->id,
            'joined_at' => now(),
        ]);

        Artisan::call('circles:backfill-admin-memberships');
        $this->assertStringContainsString('2 admin memberships backfilled', Artisan::output());

        // Organisation-community admin → organisation_member.
        $this->assertSame('organisation_member', CircleMembership::where('circle_id', $orgCircle)
            ->where('user_id', $orgAdmin->id)->whereNull('left_at')->value('internal_role'));

        // Non-organisation admin → null role.
        $this->assertNull(CircleMembership::where('circle_id', $campaignCircle)
            ->where('user_id', $campaignAdmin->id)->whereNull('left_at')->value('internal_role'));

        // The already-member admin still has exactly one active membership.
        $this->assertSame(1, CircleMembership::where('circle_id', $campaignCircle)
            ->where('user_id', $existing->id)->whereNull('left_at')->count());
    }

    public function test_it_is_idempotent(): void
    {
        $circle = $this->makeCircle(CommunityType::Campaign->value);
        $admin = User::factory()->create();
        $this->grantCircleAdmin($admin->id, $circle);

        Artisan::call('circles:backfill-admin-memberships');
        Artisan::call('circles:backfill-admin-memberships');

        $this->assertStringContainsString('0 admin memberships backfilled', Artisan::output());
        $this->assertSame(1, CircleMembership::where('user_id', $admin->id)->count());
    }
}
