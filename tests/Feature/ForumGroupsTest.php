<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\Forums\ForumGroupStatus;
use App\Enums\Forums\ForumGroupVisibility;
use App\Livewire\Communities\Services\Forums\ForumGroupModal;
use App\Livewire\Communities\Services\Forums\ForumGroupPage;
use App\Livewire\Communities\Services\Forums\ForumServiceContainer;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\User;
use App\Services\Circles\ForumService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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

    /**
     * Editing a group must not report its OWN slug as taken, or saving without
     * renaming would fail the collision check.
     */
    public function test_a_group_being_edited_does_not_collide_with_itself(): void
    {
        $service = app(ForumService::class);
        $circle = $this->makeCircle();
        $group = $service->createGroup($circle, User::factory()->create(), ['name' => 'News']);

        $this->assertTrue($service->slugTaken($circle, 'News'));
        $this->assertFalse($service->slugTaken($circle, 'News', $group->getKey()));
        $this->assertFalse($service->slugExists($circle, 'news', $group->getKey()));

        $service->createGroup($circle, User::factory()->create(), ['name' => 'Events']);
        $this->assertTrue($service->slugTaken($circle, 'Events', $group->getKey()));
    }

    /**
     * A name that yields no slug is REFUSED, not stored: the route binds by
     * slug, so an empty one is unroutable and would take the whole Forums tab
     * down. This behaviour already existed here — pinned now because the shared
     * concern makes it tempting to "fix" slugFor into never returning empty,
     * which would silently turn this message into a generated URL
     * (.scratch/polls/issues/13).
     */
    public function test_a_group_name_that_yields_no_slug_is_refused_with_a_message(): void
    {
        $circle = $this->makeCircle();
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin->fresh());

        foreach (['中文名字', '???'] as $name) {
            Livewire::test(ForumGroupModal::class, ['circleId' => $circle->id])
                ->set('name', $name)
                ->call('save')
                ->assertHasErrors('slug');
        }

        $this->assertSame(0, ForumGroup::where('circle_id', $circle->id)->count());

        // The escape hatch: supply the URL slug yourself and the name is free.
        Livewire::test(ForumGroupModal::class, ['circleId' => $circle->id])
            ->set('name', '中文名字')
            ->set('slug', 'budget-talk')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('forum_groups', ['circle_id' => $circle->id, 'slug' => 'budget-talk']);
    }

    /**
     * The same refusal on the forums side, across all three writes. The modals
     * pre-check and say so nicely; the service is the belt to those braces, so
     * a non-modal caller cannot store an unroutable slug (see
     * DerivesScopedSlugs::requireSlugFor).
     */
    public function test_the_service_refuses_a_slug_that_derives_to_nothing(): void
    {
        $service = app(ForumService::class);
        $circle = $this->makeCircle();
        $creator = User::factory()->create();
        $good = $service->createGroup($circle, $creator, ['name' => 'News']);

        $attempts = [
            'group create, derived from the name' => fn () => $service->createGroup(
                $circle,
                $creator,
                ['name' => '中文名字'],
            ),
            'group create, explicit slug' => fn () => $service->createGroup(
                $circle,
                $creator,
                ['name' => 'Budget Talk', 'slug' => '???'],
            ),
            'group update, explicit slug' => fn () => $service->updateGroup(
                $good,
                ['name' => 'News', 'slug' => '...'],
            ),
            'discussion create, derived from the title' => fn () => $service->createDiscussion(
                $good,
                $creator,
                ['title' => '中文名字'],
            ),
        ];

        foreach ($attempts as $case => $attempt) {
            try {
                $attempt();
                $this->fail("[$case] stored an unroutable empty slug");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('no usable slug', $e->getMessage(), $case);
            }
        }

        $this->assertSame(['News'], ForumGroup::where('circle_id', $circle->id)->pluck('name')->all());
        $this->assertSame('news', $good->fresh()->slug);
        $this->assertSame(0, ForumDiscussion::where('forum_group_id', $good->id)->count());
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

    public function test_participation_floor(): void
    {
        $this->assertSame(ForumGroupVisibility::Private, ForumGroupVisibility::Public->participationFloor());
        $this->assertSame(ForumGroupVisibility::Private, ForumGroupVisibility::Private->participationFloor());
        $this->assertSame(ForumGroupVisibility::Internal, ForumGroupVisibility::Internal->participationFloor());
    }

    public function test_can_view_by_visibility(): void
    {
        $circle = $this->makeCircle();
        $service = app(ForumService::class);
        $public = $service->createGroup($circle, User::factory()->create(), ['name' => 'P', 'visibility' => 'public']);
        $private = $service->createGroup($circle, User::factory()->create(), ['name' => 'Pr', 'visibility' => 'private']);
        $internal = $service->createGroup($circle, User::factory()->create(), ['name' => 'In', 'visibility' => 'internal']);

        // Visitor (no membership): only Public.
        $this->assertTrue($public->canView(null, true));
        $this->assertFalse($private->canView(null, true));
        $this->assertFalse($internal->canView(null, true));

        // Member without an approved internal role: Public + Private, not Internal.
        $member = new CircleMembership(['internal_role' => null]);
        $this->assertTrue($public->canView($member, false));
        $this->assertTrue($private->canView($member, false));
        $this->assertFalse($internal->canView($member, false));

        // Member with an approved internal role: all three.
        $internalMember = new CircleMembership(['internal_role' => 'organisation_member', 'metadata' => ['internal_role_approved' => 'approved']]);
        $this->assertTrue($internal->canView($internalMember, false));
    }

    public function test_can_participate_by_floor(): void
    {
        $circle = $this->makeCircle();
        $service = app(ForumService::class);
        $public = $service->createGroup($circle, User::factory()->create(), ['name' => 'P', 'visibility' => 'public']);
        $internal = $service->createGroup($circle, User::factory()->create(), ['name' => 'In', 'visibility' => 'internal']);

        $member = new CircleMembership(['internal_role' => null]);
        $internalMember = new CircleMembership(['internal_role' => 'organisation_member', 'metadata' => ['internal_role_approved' => 'approved']]);

        // Visitor never participates, even in a public group.
        $this->assertFalse($public->canParticipate(null, true));

        // Public group's floor is Private → any member participates.
        $this->assertTrue($public->canParticipate($member, false));

        // Internal group → only an approved-internal-role member participates.
        $this->assertFalse($internal->canParticipate($member, false));
        $this->assertTrue($internal->canParticipate($internalMember, false));
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

        // Same name again → derived slug collides → friendly error on slug, no row.
        Livewire::test(ForumGroupModal::class, ['circleId' => $circle->id])
            ->set('name', 'Announcements')
            ->call('save')
            ->assertHasErrors('slug');

        $this->assertSame(1, ForumGroup::where('circle_id', $circle->id)->count());
    }

    public function test_create_group_button_wires_up_the_open_modal_dispatch(): void
    {
        $circle = $this->makeCircle();
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin->fresh());

        // The Create Group button opens the wire-elements modal via a Blade
        // $dispatch('openModal', …) — verify that wiring is present in the render.
        Livewire::test(ForumServiceContainer::class, ['circle' => $circle])
            ->assertSee('openModal', false)
            ->assertSee('communities.services.forums.forum-group-modal', false);
    }

    public function test_container_shows_heading_visibility_and_discussions_button(): void
    {
        $circle = $this->makeCircle();
        app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge', 'visibility' => 'private']);
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin->fresh());

        Livewire::test(ForumServiceContainer::class, ['circle' => $circle])
            ->assertSee('Forum Groups')  // heading
            ->assertSee('Private')       // visibility label on the card
            ->assertSee('Discussions')   // per-card Discussions button (+ stat label)
            ->assertSee('Manage');       // manager button
    }

    public function test_modal_forbidden_for_non_managers(): void
    {
        $circle = $this->makeCircle();
        $this->actingAs(User::factory()->create()); // regular user

        // A non-manager can't even open the modal (mount aborts 403).
        Livewire::test(ForumGroupModal::class, ['circleId' => $circle->id])
            ->assertStatus(403);

        $this->assertSame(0, ForumGroup::count());
    }

    public function test_discussions_back_link_carries_the_forums_tab(): void
    {
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge']);

        $c = new ForumServiceContainer;
        $c->circle = $circle;

        // The ?from= back-link is a relative /communities/…?service=forums path
        // (so ForumGroupPage honours it and the Forums tab reselects on return).
        $this->assertStringContainsString('service%3Dforums', $c->discussionsUrl($group));
    }

    public function test_discussions_page_back_link_preselects_the_service_tab(): void
    {
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge']);
        $from = '/communities/'.$circle->id.'?service=forums';

        $this->get(route('communities.forums.show', ['circle' => $circle, 'forumGroup' => $group->slug, 'from' => $from]))
            ->assertOk()
            ->assertSee($from, false); // back link href preserves ?service=forums
    }

    public function test_the_forums_tab_survives_the_trail_out_to_a_discussion_and_back(): void
    {
        // The bug: discussionUrl() set the discussion's ?from= to the group
        // page BARE, discarding the group's own back-link. Returning to the
        // group then left it with nothing to go back to, and the next "back"
        // fell through to the community page's default tab.
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge']);
        $discussion = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'First']);

        $tabUrl = '/communities/'.$circle->id.'?service=forums';

        $page = Livewire::withQueryParams(['from' => $tabUrl])
            ->test(ForumGroupPage::class, ['circle' => $circle, 'forumGroup' => $group]);

        // The link out to the discussion must carry the tab forward.
        $discussionUrl = $page->instance()->discussionUrl($discussion);
        parse_str((string) parse_url($discussionUrl, PHP_URL_QUERY), $query);

        $this->assertStringContainsString('service=forums', urldecode($query['from'] ?? ''),
            'the discussion must be able to send you back to a group that still knows the tab');

        // And coming back through that link, the group page can still return
        // to the Forums tab.
        parse_str((string) parse_url($query['from'], PHP_URL_QUERY), $groupQuery);

        $backToTab = Livewire::withQueryParams(['from' => $groupQuery['from'] ?? null])
            ->test(ForumGroupPage::class, ['circle' => $circle, 'forumGroup' => $group])
            ->get('backUrl');

        $this->assertStringContainsString('service=forums', urldecode($backToTab));
    }

    public function test_a_group_reached_without_a_trail_still_goes_back_to_the_forums_tab(): void
    {
        // A shared link or a bookmark carries no ?from= at all.
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge']);

        $backUrl = Livewire::test(ForumGroupPage::class, ['circle' => $circle, 'forumGroup' => $group])
            ->get('backUrl');

        $this->assertStringContainsString('service=forums', urldecode($backUrl));
    }

    public function test_an_external_from_is_refused_on_the_group_page(): void
    {
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge']);

        $backUrl = Livewire::withQueryParams(['from' => 'https://example.com/phish'])
            ->test(ForumGroupPage::class, ['circle' => $circle, 'forumGroup' => $group])
            ->get('backUrl');

        $this->assertStringNotContainsString('example.com', $backUrl);
        $this->assertStringContainsString('service=forums', urldecode($backUrl));
    }

    public function test_group_page_resolves_scoped_and_lists_discussions(): void
    {
        $circle = $this->makeCircle();
        $group = app(ForumService::class)->createGroup($circle, User::factory()->create(), ['name' => 'Lounge']); // public

        $this->get(route('communities.forums.show', ['circle' => $circle, 'forumGroup' => $group->slug]))
            ->assertOk()
            ->assertSee('Lounge')
            ->assertSee('Active')             // status shown by the title
            ->assertSee('Public')             // visibility shown by the title
            ->assertSee('No discussions yet.'); // empty list state

        // A slug from another circle must NOT resolve under this circle (scoped).
        $other = $this->makeCircle();
        $this->get('/communities/'.$other->id.'/forums/'.$group->slug)->assertNotFound();
    }
}
