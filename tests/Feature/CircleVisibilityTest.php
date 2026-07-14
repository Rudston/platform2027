<?php

namespace Tests\Feature;

use App\Enums\CircleStatus;
use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Circle::isVisibleTo() — the rule the /communities/{circle} route guard and
 * the Explore visibleTo() scope both rely on: active circles are public,
 * pending circles only for platform admins/superadmins.
 */
class CircleVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();

        Schema::create('circles', function ($table): void {
            $table->id();
            $table->string('circleable_type')->nullable();
            $table->string('path')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function makeCircle(CircleStatus $status): Circle
    {
        $id = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::Organisation->value,
            'status' => $status->value,
        ]);

        return Circle::find($id);
    }

    private function makeAdmin(): User
    {
        $role = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'circle_id' => null,
        ]);

        $user = User::factory()->create();
        DB::table('model_has_roles')->insert([
            'role_id' => $role,
            'model_type' => User::class,
            'model_id' => $user->id,
            'circle_id' => null,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_active_circles_are_visible_to_everyone(): void
    {
        $circle = $this->makeCircle(CircleStatus::Active);

        $this->assertTrue($circle->isVisibleTo(null));
        $this->assertTrue($circle->isVisibleTo(User::factory()->create()));
        $this->assertTrue($circle->isVisibleTo($this->makeAdmin()));
    }

    public function test_pending_circles_are_visible_only_to_admins(): void
    {
        $circle = $this->makeCircle(CircleStatus::Pending);

        $this->assertFalse($circle->isVisibleTo(null));
        $this->assertFalse($circle->isVisibleTo(User::factory()->create()));
        $this->assertTrue($circle->isVisibleTo($this->makeAdmin()));
    }

    public function test_the_community_page_404s_for_a_guest_on_a_pending_circle(): void
    {
        // Guest (no user): mount() aborts with 404 before loading any relations.
        $circle = $this->makeCircle(CircleStatus::Pending);

        $this->get(route('communities.show', $circle))->assertNotFound();
    }
}
