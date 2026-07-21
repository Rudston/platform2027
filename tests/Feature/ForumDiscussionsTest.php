<?php

namespace Tests\Feature;

use App\Livewire\Communities\Services\Forums\ForumDiscussionModal;
use App\Livewire\Communities\Services\Forums\ForumDiscussionPage;
use App\Livewire\Communities\Services\Forums\ForumGroupPage;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
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
 * Forum Discussions — Phase 1: create gating, the create modal, list ordering,
 * the scoped detail route + visibility gating, and join/leave participation.
 */
class ForumDiscussionsTest extends TestCase
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
            $t->string('name')->nullable();
            $t->json('description')->nullable();
            $t->string('status')->default('active');
            $t->softDeletes();
            $t->timestamps();
        });

        (include database_path('migrations/2026_07_16_000001_create_circle_memberships_table.php'))->up();
        (include database_path('migrations/2026_07_16_000002_create_forum_groups_table.php'))->up();
        (include database_path('migrations/2026_07_16_000003_create_forum_discussions_table.php'))->up();
        (include database_path('migrations/2026_07_21_000001_add_content_edited_at_to_forum_discussions_table.php'))->up();
        (include database_path('migrations/2026_07_19_000001_create_forum_discussion_participants_table.php'))->up();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function makeCircle(): Circle
    {
        $id = DB::table('circles')->insertGetId(['name' => 'C']);

        return Circle::find($id);
    }

    /** A group whose creator is $creator, with the given visibility. */
    private function makeGroup(Circle $circle, ?User $creator = null, string $visibility = 'public'): ForumGroup
    {
        return ForumGroup::create([
            'circle_id' => $circle->id,
            'created_by' => $creator?->id,
            'name' => 'G'.uniqid(),
            'slug' => 'g'.uniqid(),
            'visibility' => $visibility,
        ]);
    }

    private function member(Circle $circle): User
    {
        $user = User::factory()->create();
        CircleMembership::create(['circle_id' => $circle->id, 'user_id' => $user->id, 'joined_at' => now()]);

        return $user;
    }

    public function test_can_create_discussion_gating(): void
    {
        $circle = $this->makeCircle();
        $creator = User::factory()->create();
        $group = $this->makeGroup($circle, $creator);

        $this->assertTrue($group->canCreateDiscussion($creator));   // group creator
        $this->assertFalse($group->canCreateDiscussion(User::factory()->create())); // stranger
        $this->assertFalse($group->canCreateDiscussion(null));      // guest
    }

    public function test_service_creates_discussion_with_slug_and_defaults(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $author = User::factory()->create();

        $d = app(ForumService::class)->createDiscussion($group, $author, ['title' => 'Hello World', 'content' => 'Hi']);

        $this->assertSame('hello-world', $d->slug);
        $this->assertSame($author->id, $d->created_by);
        $this->assertSame('active', $d->status->value);
        $this->assertSame('approved', $d->moderation_status->value);
        $this->assertFalse($d->is_pinned);
    }

    public function test_modal_creates_discussion_and_rejects_duplicate_slug(): void
    {
        $circle = $this->makeCircle();
        $creator = User::factory()->create();
        $group = $this->makeGroup($circle, $creator);
        $this->actingAs($creator->fresh());

        Livewire::test(ForumDiscussionModal::class, ['forumGroupId' => $group->id])
            ->set('title', 'First Topic')
            ->set('content', 'Body')
            ->call('save')
            ->assertDispatched('forum-discussions-changed');

        $this->assertDatabaseHas('forum_discussions', ['forum_group_id' => $group->id, 'slug' => 'first-topic']);

        // Same title → derived slug collides → friendly error, no second row.
        Livewire::test(ForumDiscussionModal::class, ['forumGroupId' => $group->id])
            ->set('title', 'First Topic')
            ->set('content', 'Body')
            ->call('save')
            ->assertHasErrors('slug');

        $this->assertSame(1, ForumDiscussion::where('forum_group_id', $group->id)->count());
    }

    public function test_modal_forbidden_for_non_creator(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle, User::factory()->create());
        $this->actingAs(User::factory()->create()); // not creator, not manager

        Livewire::test(ForumDiscussionModal::class, ['forumGroupId' => $group->id])->assertStatus(403);

        $this->assertSame(0, ForumDiscussion::count());
    }

    public function test_discussion_list_orders_pinned_first_then_recency(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $author = User::factory()->create();
        $service = app(ForumService::class);

        $old = $service->createDiscussion($group, $author, ['title' => 'Old']);
        $old->update(['created_at' => now()->subDays(3)]);
        $new = $service->createDiscussion($group, $author, ['title' => 'New']);
        $pinned = $service->createDiscussion($group, $author, ['title' => 'Pinned']);
        $pinned->update(['is_pinned' => true, 'created_at' => now()->subDays(5)]);

        $page = new ForumGroupPage;
        $page->circle = $circle;
        $page->group = $group;

        // Pinned first (despite being oldest), then newest → oldest.
        $this->assertSame(['Pinned', 'New', 'Old'], $page->discussions()->pluck('title')->all());
    }

    public function test_detail_route_scoped_binding(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);            // public
        $other = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'Topic']);

        // Resolves within its own group (public → viewable by a guest).
        $this->get(route('communities.forums.discussions.show', [
            'circle' => $circle, 'forumGroup' => $group->slug, 'forumDiscussion' => $d->slug,
        ]))->assertOk()->assertSee('Topic');

        // Not resolvable under a different group (scoped binding → 404).
        $this->get('/communities/'.$circle->id.'/forums/'.$other->slug.'/'.$d->slug)->assertNotFound();
    }

    public function test_detail_visibility_gating(): void
    {
        $circle = $this->makeCircle();
        $internal = $this->makeGroup($circle, null, 'internal');
        $d = app(ForumService::class)->createDiscussion($internal, User::factory()->create(), ['title' => 'Secret']);

        // Guest cannot view an internal group's discussion.
        $this->get(route('communities.forums.discussions.show', [
            'circle' => $circle, 'forumGroup' => $internal->slug, 'forumDiscussion' => $d->slug,
        ]))->assertNotFound();
    }

    public function test_join_and_leave_on_detail_page(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);            // public → any member participates
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'Topic']);
        $member = $this->member($circle);

        $this->actingAs($member->fresh());
        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        $this->assertTrue($page->canParticipate());
        $this->assertFalse($page->isJoined());

        $page->join();
        $this->assertTrue($page->isJoined());
        $this->assertSame(1, $page->participantCount());

        $page->leave();
        $this->assertFalse($page->isJoined());
        $this->assertSame(0, $page->participantCount());
    }

    public function test_author_can_edit_first_post_and_it_marks_edited(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $author = User::factory()->create();
        $d = app(ForumService::class)->createDiscussion($group, $author, ['title' => 'Topic', 'content' => 'Original']);

        $this->assertFalse($d->isEdited());
        $this->assertTrue($d->canEditContentBy($author));

        $this->actingAs($author->fresh());
        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        $page->startEditingContent();
        $this->assertTrue($page->editingContent);
        $this->assertSame('Original', $page->draftContent);

        $page->draftContent = 'Revised';
        $page->saveContent();

        $fresh = $d->fresh();
        $this->assertSame('Revised', $fresh->content);
        $this->assertTrue($fresh->isEdited());
        $this->assertFalse($page->editingContent);
    }

    public function test_non_author_cannot_edit_first_post(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $author = User::factory()->create();
        $d = app(ForumService::class)->createDiscussion($group, $author, ['title' => 'Topic', 'content' => 'Original']);

        // Even a circle manager (not the author) can't edit the content.
        $manager = User::factory()->create();
        DB::table('roles')->insert(['name' => 'admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', 'admin')->value('id'), 'model_type' => User::class, 'model_id' => $manager->id, 'circle_id' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse($d->canEditContentBy($manager->fresh()));

        $this->actingAs($manager->fresh());
        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        $page->draftContent = 'Hacked';
        $page->saveContent(); // guarded no-op

        $this->assertSame('Original', $d->fresh()->content);
        $this->assertFalse($d->fresh()->isEdited());
    }

    public function test_visitor_cannot_participate(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'Topic']);

        // A logged-in NON-member (visitor) can't participate even in a public group.
        $this->actingAs(User::factory()->create());
        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        $this->assertFalse($page->canParticipate());
        $page->join(); // guarded no-op
        $this->assertSame(0, $page->participantCount());
    }
}
