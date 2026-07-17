<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\Forums\ForumGroupStatus;
use App\Enums\Forums\ForumGroupVisibility;
use App\Livewire\Communities\Services\ForumGroupModal;
use App\Livewire\Communities\Services\ForumServiceContainer;
use App\Models\Circles\Circle;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\User;
use App\Services\Circles\ForumService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Forum groups: service CRUD + slug uniqueness, the manage-authorization check,
 * the overview container (stats/filter/counts), the create/edit modal, and the
 * Discussions route (scoped binding + back-link).
 */
class ForumGroupsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();

        Schema::create('circles', function ($t): void {
            $t->id();
            $t->string('circleable_type')->nullable();
            $t->unsignedBigInteger('circleable_id')->nullable();
            $t->string('path')->nullable();
            $t->string('name')->nullable();
            $t->json('description')->nullable();
            $t->string('status')->default('active');
            $t->softDeletes();
            $t->timestamps();
        });

        (include database_path('migrations/2026_07_16_000002_create_forum_groups_table.php'))->up();
        (include database_path('migrations/2026_07_16_000003_create_forum_discussions_table.php'))->up();

        // The overview eager-loads group tags, so the tagging tables must exist.
        Schema::create('themes', function ($t): void {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->timestamps();
        });
        (include database_path('migrations/2026_07_17_000001_create_taggables_table.php'))->up();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function makeCircle(): Circle
    {
        $id = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'name' => 'A Place',
        ]);

        return Circle::find($id);
    }

    private function grantGlobalRole(User $user, string $role): void
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id, 'circle_id' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantCircleAdmin(User $user, int $circleId): void
    {
        $roleId = DB::table('roles')->where('name', 'circle_admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id, 'circle_id' => $circleId]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_service_creates_a_group_with_a_slug(): void
    {
        $circle = $this->makeCircle();
        $user = User::factory()->create();

        $group = app(ForumService::class)->createGroup($circle, $user, [
            'name' => 'General Chat',
            'description' => 'Anything goes',
            'visibility' => 'private',
        ]);

        $this->assertSame($circle->id, $group->circle_id);
        $this->assertSame($user->id, $group->created_by);
        $this->assertSame('general-chat', $group->slug);
        $this->assertSame(ForumGroupStatus::Active, $group->status);
        $this->assertSame(ForumGroupVisibility::Private, $group->visibility);
    }

    public function test_slug_uniqueness_is_scoped_to_the_circle(): void
    {
        $service = app(ForumService::class);
        $circle = $this->makeCircle();
        $other = $this->makeCircle();
        $service->createGroup($circle, User::factory()->create(), ['name' => 'News']);

        $this->assertTrue($service->slugTaken($circle, 'News'));
        $this->assertFalse($service->slugTaken($circle, 'Events'));
        $this->assertFalse($service->slugTaken($other, 'News')); // same name, different circle
    }

    public function test_deactivate_group(): void
    {
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'G']);

        app(ForumService::class)->deactivateGroup($group);

        $this->assertSame(ForumGroupStatus::Deactivated, $group->fresh()->status);
    }

    public function test_is_manageable_by(): void
    {
        $circle = $this->makeCircle();
        $other = $this->makeCircle();

        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $circleAdmin = User::factory()->create();
        $this->grantCircleAdmin($circleAdmin, $circle->id);
        $otherAdmin = User::factory()->create();
        $this->grantCircleAdmin($otherAdmin, $other->id);
        $regular = User::factory()->create();

        $this->assertTrue($circle->isManageableBy($admin->fresh()));
        $this->assertTrue($circle->isManageableBy($circleAdmin->fresh()));
        $this->assertFalse($circle->isManageableBy($otherAdmin->fresh())); // admins a DIFFERENT circle
        $this->assertFalse($circle->isManageableBy($regular->fresh()));
        $this->assertFalse($circle->isManageableBy(null));
    }

    public function test_container_stats_filter_and_counts(): void
    {
        $circle = $this->makeCircle();
        $service = app(ForumService::class);
        $active1 = $service->createGroup($circle, User::factory()->create(), ['name' => 'Alpha']);
        $service->createGroup($circle, User::factory()->create(), ['name' => 'Beta']);
        $deactivated = $service->createGroup($circle, User::factory()->create(), ['name' => 'Gamma']);
        $service->deactivateGroup($deactivated);

        // Two discussions in Alpha.
        foreach (['t1', 't2'] as $t) {
            ForumDiscussion::create(['forum_group_id' => $active1->id, 'title' => $t, 'content' => 'c']);
        }

        $c = new ForumServiceContainer;
        $c->circle = $circle;

        $this->assertSame(3, $c->totalGroups());          // all statuses
        $this->assertSame(2, $c->groups()->count());      // default filter = active only
        $this->assertSame(2, $c->totalDiscussions());     // across the circle

        $c->statusFilter = 'all';
        $this->assertSame(3, $c->groups()->count());

        $c->statusFilter = 'active';
        $c->search = 'alph';
        $this->assertSame(1, $c->groups()->count());
        $this->assertSame(2, $c->groups()->first()->discussions_count);
    }

    public function test_modal_creates_group_and_rejects_duplicate_name(): void
    {
        $circle = $this->makeCircle();
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin->fresh());

        Livewire::test(ForumGroupModal::class, ['circleId' => $circle->id])
            ->set('name', 'Announcements')
            ->set('visibility', 'public')
            ->call('save')
            ->assertDispatched('forum-groups-changed');

        $this->assertDatabaseHas('forum_groups', ['circle_id' => $circle->id, 'slug' => 'announcements']);

        // Same name again → friendly validation error, no second row.
        Livewire::test(ForumGroupModal::class, ['circleId' => $circle->id])
            ->set('name', 'Announcements')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(1, ForumGroup::where('circle_id', $circle->id)->count());
    }

    public function test_modal_forbidden_for_non_managers(): void
    {
        $circle = $this->makeCircle();
        $this->actingAs(User::factory()->create()); // regular user

        Livewire::test(ForumGroupModal::class, ['circleId' => $circle->id])
            ->set('name', 'Sneaky')
            ->call('save')
            ->assertStatus(403);

        $this->assertSame(0, ForumGroup::count());
    }

    public function test_discussions_route_resolves_scoped_and_shows_placeholder(): void
    {
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge']);

        $this->get(route('communities.forums.show', ['circle' => $circle, 'forumGroup' => $group->slug]))
            ->assertOk()
            ->assertSee('Lounge')
            ->assertSee('coming soon');

        // A slug from another circle must NOT resolve under this circle (scoped).
        $other = $this->makeCircle();
        $this->get('/communities/'.$other->id.'/forums/'.$group->slug)->assertNotFound();
    }
}
