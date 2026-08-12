<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Livewire\Dashboard\DashboardCommunities;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Circles\CircleVisit;
use App\Models\User;
use App\Support\Circles\ExplorerBreadcrumb;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * User dashboard: per-section routing (server-side, bookmarkable) and the real
 * "My Communities" section (role-grouped, geography-sorted memberships).
 */
class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();

        Schema::create('circles', function ($t): void {
            $t->id();
            $t->foreignId('parent_id')->nullable();
            $t->string('name')->nullable();
            $t->string('path')->nullable();
            $t->integer('depth')->default(0);
            $t->string('status')->default('active');
            $t->string('circleable_type')->nullable();
            $t->unsignedBigInteger('circleable_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        (include database_path('migrations/2026_07_16_000001_create_circle_memberships_table.php'))->up();
        (include database_path('migrations/2026_07_23_000003_create_circle_visits_table.php'))->up();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function makeCircle(string $name, string $path, int $depth, ?int $parentId = null, ?string $circleableType = null): Circle
    {
        $id = DB::table('circles')->insertGetId([
            'parent_id' => $parentId,
            'name' => $name,
            'depth' => $depth,
            'circleable_type' => $circleableType,
        ]);
        DB::table('circles')->where('id', $id)->update(['path' => str_replace('#', (string) $id, $path)]);

        return Circle::find($id);
    }

    private function joinAsMember(User $user, Circle $circle): void
    {
        CircleMembership::create(['circle_id' => $circle->id, 'user_id' => $user->id, 'joined_at' => now()]);
    }

    private function grantCircleAdmin(User $user, Circle $circle): void
    {
        $roleId = DB::table('roles')->where('name', 'circle_admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id, 'circle_id' => $circle->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dashboard_redirects_to_news(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect(route('dashboard.news'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard/news')->assertRedirect(route('login'));
    }

    public function test_each_section_renders(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['news', 'calendar', 'communities', 'campaigns', 'voting'] as $section) {
            $this->get(route("dashboard.{$section}"))->assertOk();
        }
    }

    public function test_my_communities_groups_by_role_and_sorts_by_geography(): void
    {
        $national = $this->makeCircle('South Africa', '#', 0);
        $province = $this->makeCircle('Western Cape', "{$national->id}/#", 1, $national->id);
        $adminCircle = $this->makeCircle('Garden Route', "{$national->id}/{$province->id}/#", 2, $province->id);
        $memberCircle = $this->makeCircle('Cape Metro', "{$national->id}/{$province->id}/#", 2, $province->id);

        $user = User::factory()->create();
        $this->joinAsMember($user, $adminCircle);
        $this->joinAsMember($user, $memberCircle);
        $this->grantCircleAdmin($user, $adminCircle); // admin of Garden Route only

        $this->actingAs($user->fresh());

        Livewire::test(DashboardCommunities::class)
            ->assertSeeInOrder([
                "Where you're an admin",
                'Garden Route',
                "Where you're a member",
                'Cape Metro',
            ])
            // Breadcrumb ancestors render for the admin row.
            ->assertSee('South Africa')
            ->assertSee('Western Cape')
            // Role badges.
            ->assertSee('admin')
            ->assertSee('member');

        // Grouping is by circle_admin role, not global roles.
        $rows = Livewire::test(DashboardCommunities::class)->instance()->groups();
        $this->assertSame([$adminCircle->id], $rows['admin']->pluck('circle.id')->all());
        $this->assertSame([$memberCircle->id], $rows['member']->pluck('circle.id')->all());
    }

    public function test_my_communities_breadcrumbs_link_into_the_explorer(): void
    {
        $national = $this->makeCircle('South Africa', '#', 0, null, CommunityType::LocationCommunity->value);
        $province = $this->makeCircle('Western Cape', "{$national->id}/#", 1, $national->id, CommunityType::LocationCommunity->value);
        $place = $this->makeCircle('Cape Metro', "{$national->id}/{$province->id}/#", 2, $province->id, CommunityType::LocationCommunity->value);
        $org = $this->makeCircle('Habitat SA', "{$national->id}/{$province->id}/{$place->id}/#", 3, $place->id, CommunityType::Organisation->value);

        $user = User::factory()->create();
        $this->joinAsMember($user, $place);
        $this->joinAsMember($user, $org);

        $this->actingAs($user->fresh());

        // A location community's trail ends at its PARENT place; a
        // sub-community's closes with the type crumb. Neither repeats its own
        // name — the row already shows that as the linked heading below.
        $this->assertSame([
            ['name' => 'South Africa', 'url' => route('explore', absolute: false)],
            ['name' => 'Western Cape', 'url' => route('explore', ['circle' => $province->id], absolute: false)],
        ], ExplorerBreadcrumb::for($place));

        $this->assertSame([
            ['name' => 'South Africa', 'url' => route('explore', absolute: false)],
            ['name' => 'Western Cape', 'url' => route('explore', ['circle' => $province->id], absolute: false)],
            ['name' => 'Cape Metro', 'url' => route('explore', ['circle' => $place->id], absolute: false)],
            ['name' => 'Organisations', 'url' => route('explore', ['circle' => $place->id, 'community' => 'Organisation'], absolute: false)],
        ], ExplorerBreadcrumb::for($org));

        Livewire::test(DashboardCommunities::class)
            ->assertSeeInOrder(['South Africa', 'Western Cape', 'Cape Metro', 'Organisations', 'Habitat SA'])
            // Place crumbs open the explorer at that location, not the circle page.
            ->assertSee('href="'.route('explore', absolute: false).'"', escape: false)
            ->assertSee('href="'.route('explore', ['circle' => $province->id], absolute: false).'"', escape: false)
            // The type crumb also selects the explorer's community-type filter.
            ->assertSee('circle='.$place->id.'&amp;community=Organisation', escape: false)
            ->assertDontSee('href="'.route('communities.show', $province).'"', escape: false);
    }

    public function test_circle_visit_record_is_idempotent_per_user_circle(): void
    {
        $user = User::factory()->create();
        $circle = $this->makeCircle('X', '#', 0);

        CircleVisit::record($user, $circle);
        CircleVisit::record($user, $circle);

        $this->assertSame(1, CircleVisit::where('user_id', $user->id)->where('circle_id', $circle->id)->count());
    }

    public function test_recently_visited_excludes_members_orders_by_recency_and_caps_at_8(): void
    {
        $user = User::factory()->create();

        $memberCircle = $this->makeCircle('Member Place', '#', 0);
        $this->joinAsMember($user, $memberCircle);

        // 10 visited non-member circles; higher i = more recent.
        $visited = [];
        foreach (range(1, 10) as $i) {
            $c = $this->makeCircle("Visited {$i}", '#', 0);
            CircleVisit::create(['user_id' => $user->id, 'circle_id' => $c->id, 'last_visited_at' => now()->subMinutes(60 - $i)]);
            $visited[$i] = $c;
        }
        // Also visited a circle they're a member of — must be excluded from the list.
        CircleVisit::create(['user_id' => $user->id, 'circle_id' => $memberCircle->id, 'last_visited_at' => now()]);

        $this->actingAs($user->fresh());
        $rows = Livewire::test(DashboardCommunities::class)->instance()->recentlyVisited();

        $ids = $rows->pluck('circle.id')->all();
        $this->assertCount(8, $rows);                       // capped at 8
        $this->assertNotContains($memberCircle->id, $ids);  // members excluded
        $this->assertSame($visited[10]->id, $ids[0]);       // most recent first
        $this->assertSame($visited[9]->id, $ids[1]);
    }

    public function test_empty_admin_group_heading_is_hidden(): void
    {
        $circle = $this->makeCircle('Somewhere', '#', 0);
        $user = User::factory()->create();
        $this->joinAsMember($user, $circle); // member only, no admin role
        $this->actingAs($user->fresh());

        Livewire::test(DashboardCommunities::class)
            ->assertDontSee("Where you're an admin")
            ->assertSee("Where you're a member");
    }
}
