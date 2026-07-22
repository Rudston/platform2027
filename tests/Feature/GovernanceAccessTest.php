<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CommentModerationRecords\CommentModerationRecordResource;
use App\Filament\Resources\ContentBlocks\ContentBlockResource;
use App\Filament\Resources\Requests\RequestResource;
use App\Models\Circles\Circle;
use App\Models\Communication\Request;
use App\Models\User;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * circle_admin access to the Filament Governance panel:
 *   - canAccessPanel() admits any circle_admin (role held on any team),
 *   - the Platform/Communication resources stay admin/superadmin only,
 *   - RequestResource's query is scoped to a circle_admin's directed requests
 *     plus their administered subtree (that circle or a descendant).
 *
 * Tables are hand-built (the full migration set can't run on sqlite); circles
 * is minimal since the scoping only needs id/path/status.
 */
class GovernanceAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        $this->buildCirclesTable();
        (include database_path('migrations/2026_07_07_000003_create_requests_table.php'))->up();
        (include database_path('migrations/2026_07_14_000001_add_responsible_admin_id_to_requests_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function buildCirclesTable(): void
    {
        Schema::create('circles', function ($table): void {
            $table->id();
            $table->string('circleable_type')->nullable();
            $table->string('path')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function makeCircle(?int $parentId, ?string $parentPath): Circle
    {
        $id = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
        ]);
        $path = $parentPath ? "{$parentPath}/{$id}" : (string) $id;
        DB::table('circles')->where('id', $id)->update(['path' => $path]);

        return Circle::find($id);
    }

    private function ensureRole(string $name): int
    {
        $existing = DB::table('roles')->where('name', $name)->value('id');

        return $existing ?? DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'circle_id' => null,
        ]);
    }

    private function grantGlobalRole(User $user, string $name): void
    {
        DB::table('model_has_roles')->insert([
            'role_id' => $this->ensureRole($name),
            'model_type' => User::class,
            'model_id' => $user->id,
            'circle_id' => null,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantCircleAdmin(User $user, int $circleId): void
    {
        DB::table('model_has_roles')->insert([
            'role_id' => $this->ensureRole('circle_admin'),
            'model_type' => User::class,
            'model_id' => $user->id,
            'circle_id' => $circleId,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function makeRequest(int $circleId, ?int $responsibleAdminId, User $requester): Request
    {
        return Request::create([
            'type' => 'organisation_approval',
            'status' => 'pending',
            'direction' => 'external',
            'requester_id' => $requester->id,
            'circle_id' => $circleId,
            'responsible_admin_id' => $responsibleAdminId,
        ]);
    }

    public function test_administered_by_returns_the_users_circles(): void
    {
        $province = $this->makeCircle(null, null);
        $place = $this->makeCircle($province->id, $province->path);

        $user = User::factory()->create();
        $this->grantCircleAdmin($user, $place->id);

        $administered = Circle::administeredBy($user);
        $this->assertEqualsCanonicalizing([$place->id], $administered->pluck('id')->all());
        $this->assertTrue(Circle::administeredBy(null)->isEmpty());
    }

    public function test_scope_manageable_by_matches_the_single_record_check(): void
    {
        $a = $this->makeCircle(null, null);
        $b = $this->makeCircle(null, null);
        $c = $this->makeCircle(null, null);

        $circleAdmin = User::factory()->create();
        $this->grantCircleAdmin($circleAdmin, $a->id);

        // circle_admin: scope returns only their circle, matching isManageableBy.
        $this->assertEqualsCanonicalizing(
            [$a->id],
            Circle::query()->manageableBy($circleAdmin)->pluck('id')->all(),
        );
        $this->assertTrue($a->isManageableBy($circleAdmin));
        $this->assertFalse($b->isManageableBy($circleAdmin));

        // global admin: everything, unfiltered.
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id],
            Circle::query()->manageableBy($admin)->pluck('id')->all(),
        );

        // no manage role, and a guest: no circles.
        $this->assertSame([], Circle::query()->manageableBy(User::factory()->create())->pluck('id')->all());
        $this->assertSame([], Circle::query()->manageableBy(null)->pluck('id')->all());
    }

    public function test_comment_moderation_resource_is_admin_only_for_now(): void
    {
        $circle = $this->makeCircle(null, null);

        // circle_admin: not yet (scoping is a deferred stewardship follow-up).
        $circleAdmin = User::factory()->create();
        $this->grantCircleAdmin($circleAdmin, $circle->id);
        $this->actingAs($circleAdmin->fresh());
        $this->assertFalse(CommentModerationRecordResource::canViewAny());

        // global admin: yes.
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin->fresh());
        $this->assertTrue(CommentModerationRecordResource::canViewAny());

        // ordinary user: no.
        $this->actingAs(User::factory()->create());
        $this->assertFalse(CommentModerationRecordResource::canViewAny());
    }

    public function test_panel_access_is_granted_to_admins_and_circle_admins_only(): void
    {
        $circle = $this->makeCircle(null, null);
        $panel = Panel::make();

        $regular = User::factory()->create();
        $this->assertFalse($regular->canAccessPanel($panel));

        $circleAdmin = User::factory()->create();
        $this->grantCircleAdmin($circleAdmin, $circle->id);
        $this->assertTrue($circleAdmin->canAccessPanel($panel));

        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->assertTrue($admin->canAccessPanel($panel));
    }

    public function test_platform_resources_stay_admin_only(): void
    {
        $circle = $this->makeCircle(null, null);

        $circleAdmin = User::factory()->create();
        $this->grantCircleAdmin($circleAdmin, $circle->id);
        $this->actingAs($circleAdmin);
        $this->assertFalse(ContentBlockResource::canViewAny());
        $this->assertTrue(RequestResource::canViewAny());
        // Dashboard stays accessible (so /admin never 403s) but is hidden from
        // the circle_admin's nav; they're redirected to Requests on arrival.
        $this->assertTrue(Dashboard::canAccess());
        $this->assertFalse(Dashboard::shouldRegisterNavigation());

        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin);
        $this->assertTrue(ContentBlockResource::canViewAny());
        $this->assertTrue(Dashboard::shouldRegisterNavigation());
    }

    public function test_request_query_is_scoped_to_a_circle_admins_subtree(): void
    {
        // province > place > org ; plus an unrelated circle.
        $province = $this->makeCircle(null, null);
        $place = $this->makeCircle($province->id, $province->path);
        $org = $this->makeCircle($place->id, $place->path);
        $unrelated = $this->makeCircle(null, null);

        $requester = User::factory()->create();
        $circleAdmin = User::factory()->create();
        $this->grantCircleAdmin($circleAdmin, $place->id); // administers "place"

        $rDescendant = $this->makeRequest($org->id, null, $requester);          // in subtree → visible
        $rSelf = $this->makeRequest($place->id, null, $requester);              // administered circle → visible
        $rDirected = $this->makeRequest($unrelated->id, $circleAdmin->id, $requester); // directed → visible
        $rAncestor = $this->makeRequest($province->id, null, $requester);       // ancestor → NOT visible
        $rUnrelated = $this->makeRequest($unrelated->id, null, $requester);     // unrelated → NOT visible

        $this->actingAs($circleAdmin);
        $visible = RequestResource::getEloquentQuery()->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$rDescendant->id, $rSelf->id, $rDirected->id],
            $visible,
        );

        // An admin sees everything, unscoped.
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin);
        $this->assertCount(5, RequestResource::getEloquentQuery()->get());
    }
}
