<?php

namespace Tests\Feature;

use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationFlagSource;
use App\Filament\Resources\CommentModerationRecords\CommentModerationRecordResource;
use App\Livewire\Communities\Services\Forums\ForumDiscussionModal;
use App\Livewire\Communities\Services\Forums\ForumDiscussionPage;
use App\Livewire\Communities\Services\Forums\ForumGroupPage;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Comment;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Moderation\CommentModerationRecord;
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
        (include database_path('migrations/2026_07_21_000002_create_comments_table.php'))->up();
        (include database_path('migrations/2026_07_21_000005_add_delete_edit_columns_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_23_000002_add_last_edited_by_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_22_000001_add_moderation_columns_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_22_000002_create_comment_moderation_records_table.php'))->up();
        (include database_path('migrations/2026_07_23_000001_add_snapshot_columns_to_comment_moderation_records_table.php'))->up();
        (include database_path('migrations/2026_07_21_000003_create_likes_table.php'))->up();

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

    /** A member who is also a circle_admin of $circle (so isManageableBy is true). */
    private function makeManager(Circle $circle): User
    {
        $user = $this->member($circle);
        $roleId = DB::table('roles')->insertGetId(['name' => 'circle_admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => (new User)->getMorphClass(),
            'model_id' => $user->id,
            'circle_id' => $circle->id,
        ]);

        return $user;
    }

    private function pageFor(Circle $circle, ForumGroup $group, ForumDiscussion $d): ForumDiscussionPage
    {
        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        return $page;
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

    public function test_participant_count_on_detail_page_reflects_contributions(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);            // public → any member participates
        $creator = User::factory()->create();
        $d = app(ForumService::class)->createDiscussion($group, $creator, ['title' => 'Topic']);
        $member = $this->member($circle);

        $this->actingAs($member->fresh());
        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        // Just the creator so far.
        $this->assertSame(1, $page->participantCount());

        // The member posts a response → becomes a contributor → 2 participants.
        $page->newRootContent = 'Hello';
        $page->postRoot();
        $this->assertSame(2, $page->participantCount());
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

    public function test_participant_can_post_root_and_reply(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle); // public
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $member = $this->member($circle);
        $this->actingAs($member->fresh());

        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        // Root response.
        $page->newRootContent = 'First response';
        $page->postRoot();
        $root = $d->comments()->whereNull('parent_id')->first();
        $this->assertNotNull($root);
        $this->assertSame('First response', $root->content);
        $this->assertSame('', $page->newRootContent);

        // Reply to it.
        $page->reply($root->id);
        $this->assertSame($root->id, $page->replyingToId);
        $page->replyContent = 'A reply';
        $page->postReply();
        $reply = $d->comments()->where('parent_id', $root->id)->first();
        $this->assertNotNull($reply);
        $this->assertSame('A reply', $reply->content);
        $this->assertNull($page->replyingToId); // composer closed after posting
    }

    public function test_view_only_visitor_cannot_post_or_like(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $author = User::factory()->create();
        $d = app(ForumService::class)->createDiscussion($group, $author, ['title' => 'T']);
        $comment = $d->comments()->create(['user_id' => $author->id, 'content' => 'hi']);

        // A logged-in NON-member (can view public, cannot participate).
        $this->actingAs(User::factory()->create());
        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        $this->assertFalse($page->canParticipate());

        $page->newRootContent = 'Nope';
        $page->postRoot();               // guarded no-op
        $page->toggleLike($comment->id); // guarded no-op

        $this->assertSame(1, $d->comments()->count()); // only the author's comment
        $this->assertSame(0, $comment->likes()->count());
        // ...but the thread is still readable.
        $this->assertCount(1, $page->responses()['roots']);
    }

    public function test_like_toggle(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $comment = $d->comments()->create(['user_id' => User::factory()->create()->id, 'content' => 'hi']);
        $member = $this->member($circle);
        $this->actingAs($member->fresh());

        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        $page->toggleLike($comment->id);
        $this->assertSame(1, $comment->likes()->count());

        $page->toggleLike($comment->id); // toggles off
        $this->assertSame(0, $comment->likes()->count());
    }

    public function test_responses_order_pinned_first_and_exclude_hidden(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $author = User::factory()->create();
        $d = app(ForumService::class)->createDiscussion($group, $author, ['title' => 'T']);

        $a = $d->comments()->create(['user_id' => $author->id, 'content' => 'A']);
        $a->update(['created_at' => now()->subDays(3)]);
        $b = $d->comments()->create(['user_id' => $author->id, 'content' => 'B']);
        $b->update(['created_at' => now()->subDay()]);
        $pinned = $d->comments()->create(['user_id' => $author->id, 'content' => 'P', 'pinned' => true, 'pinned_position' => 1]);
        $pinned->update(['created_at' => now()]); // newest, but pinned → first
        $d->comments()->create(['user_id' => $author->id, 'content' => 'Hidden', 'hidden' => true]);

        $page = new ForumDiscussionPage;
        $page->circle = $circle;
        $page->group = $group;
        $page->discussion = $d;

        $roots = $page->responses()['roots'];
        // Pinned first, then the rest by created_at asc; hidden excluded.
        $this->assertSame(['P', 'A', 'B'], $roots->pluck('content')->all());
    }

    public function test_discussion_participant_count_is_creator_plus_unique_commenters(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $creator = User::factory()->create();
        $d = app(ForumService::class)->createDiscussion($group, $creator, ['title' => 'T']);

        // Just the creator, before anyone comments.
        $this->assertSame(1, $d->participantCount());

        // A different user comments → 2 unique users.
        $b = User::factory()->create();
        $d->comments()->create(['user_id' => $b->id, 'content' => 'x']);
        $this->assertSame(2, $d->participantCount());

        // The creator comments (twice) → still 2 (she isn't counted again).
        $d->comments()->create(['user_id' => $creator->id, 'content' => 'y']);
        $d->comments()->create(['user_id' => $creator->id, 'content' => 'z']);
        $this->assertSame(2, $d->participantCount());

        // A third user comments twice → 3 unique users.
        $c = User::factory()->create();
        $d->comments()->create(['user_id' => $c->id, 'content' => 'p']);
        $d->comments()->create(['user_id' => $c->id, 'content' => 'q']);
        $this->assertSame(3, $d->participantCount());
    }

    public function test_group_participant_count_sums_child_discussions(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        // D1: creator A, commenter B → {A, B} = 2
        $d1 = app(ForumService::class)->createDiscussion($group, $a, ['title' => 'D1']);
        $d1->comments()->create(['user_id' => $b->id, 'content' => 'x']);

        // D2: creator B, commenters A and C → {B, A, C} = 3
        $d2 = app(ForumService::class)->createDiscussion($group, $b, ['title' => 'D2']);
        $d2->comments()->create(['user_id' => $a->id, 'content' => 'y']);
        $d2->comments()->create(['user_id' => $c->id, 'content' => 'z']);

        // Sum across discussions (A and B are counted in each). 2 + 3 = 5.
        $this->assertSame(5, $group->participantCount());

        // An empty group has no participants.
        $this->assertSame(0, $this->makeGroup($circle)->participantCount());
    }

    public function test_author_can_delete_own_comment_via_page(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $member = $this->member($circle);
        $this->actingAs($member->fresh());

        $page = $this->pageFor($circle, $group, $d);
        $page->newRootContent = 'mine';
        $page->postRoot();
        $comment = $d->comments()->first();

        $page->deleteComment($comment->id);
        $this->assertNull(Comment::find($comment->id)); // no replies → hard delete
    }

    public function test_manager_can_delete_others_comment(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $comment = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'hi']);

        $manager = $this->makeManager($circle);
        $this->actingAs($manager->fresh());

        $page = $this->pageFor($circle, $group, $d);
        $this->assertTrue($page->canManageThread());
        $page->deleteComment($comment->id);
        $this->assertNull(Comment::find($comment->id));
    }

    public function test_non_author_non_manager_cannot_delete(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $comment = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'hi']);

        $this->actingAs($this->member($circle)->fresh()); // participant, not author/manager
        $page = $this->pageFor($circle, $group, $d);
        $page->deleteComment($comment->id);

        $this->assertNotNull(Comment::find($comment->id)); // untouched
    }

    public function test_edit_is_author_only_via_page(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $author = $this->member($circle);
        $comment = $d->comments()->create(['user_id' => $author->id, 'content' => 'first']);

        // Author edits → content changes, edited_at stamped.
        $this->actingAs($author->fresh());
        $page = $this->pageFor($circle, $group, $d);
        $page->startEditingComment($comment->id);
        $this->assertSame($comment->id, $page->editingCommentId);
        $page->editContent = 'second';
        $page->saveComment();
        $this->assertSame('second', $comment->fresh()->content);
        $this->assertTrue($comment->fresh()->isEdited());
        $this->assertNull($page->editingCommentId);

        // A manager may delete but NOT edit someone else's words.
        $manager = $this->makeManager($circle);
        $this->actingAs($manager->fresh());
        $page2 = $this->pageFor($circle, $group, $d);
        $page2->startEditingComment($comment->id);
        $this->assertNull($page2->editingCommentId); // refused
    }

    public function test_flag_sets_bool_idempotently_and_not_on_own(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $comment = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'hi']);

        $flagger = $this->member($circle);
        $this->actingAs($flagger->fresh());
        $page = $this->pageFor($circle, $group, $d);

        $page->flag($comment->id);
        $this->assertTrue($comment->fresh()->flagged_as_offensive);
        $this->assertContains($comment->id, $page->flaggedByMe);
        // A user flag also opens a pending User-sourced moderation record.
        $this->assertSame(1, $comment->moderationRecords()->where('flagged_by', 'user')->count());

        $page->flag($comment->id); // idempotent — bool stays set, no duplicate record
        $this->assertTrue($comment->fresh()->flagged_as_offensive);
        $this->assertSame(1, $comment->moderationRecords()->where('flagged_by', 'user')->count());

        // Flagging your own comment is a no-op (no bool, no record).
        $own = $d->comments()->create(['user_id' => $flagger->id, 'content' => 'mine']);
        $page->flag($own->id);
        $this->assertFalse($own->fresh()->flagged_as_offensive);
        $this->assertSame(0, $own->moderationRecords()->count());
    }

    public function test_manager_can_hide_a_comment_and_author_cannot(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $author = $this->member($circle);
        $comment = $d->comments()->create(['user_id' => $author->id, 'content' => 'visible text']);

        // The author is not a moderator — hiding their own comment is a no-op.
        $this->actingAs($author->fresh());
        $this->pageFor($circle, $group, $d)->hideComment($comment->id);
        $this->assertFalse($comment->fresh()->hidden);

        // A circle manager can hide it.
        $manager = $this->makeManager($circle);
        $this->actingAs($manager->fresh());
        $page = $this->pageFor($circle, $group, $d);
        $page->hideComment($comment->id);

        $comment->refresh();
        $this->assertTrue($comment->hidden);
        $this->assertNotNull($comment->hidden_at);
        $this->assertSame($manager->id, $comment->hidden_by_user_id);

        // Hidden comment is excluded from the rendered thread.
        $this->assertNotContains('visible text', $page->responses()['roots']->pluck('content')->all());
    }

    public function test_moderation_record_resolutions_act_on_the_comment(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $manager = $this->makeManager($circle);

        // Hide resolution.
        $c1 = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'hide me']);
        $r1 = CommentModerationRecord::open($c1, ModerationFlagSource::User);
        $r1->resolveHidden($manager);
        $this->assertTrue($c1->fresh()->hidden);
        $r1->refresh();
        $this->assertTrue($r1->moderated);
        $this->assertSame(ModerationAction::Hidden, $r1->moderation_action);
        $this->assertSame($manager->id, $r1->moderated_by_user_id);

        // Delete resolution on a comment WITH a reply → tombstone; record survives.
        $c2 = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'delete me']);
        $d->comments()->create(['user_id' => $this->member($circle)->id, 'parent_id' => $c2->id, 'content' => 'reply']);
        $r2 = CommentModerationRecord::open($c2, ModerationFlagSource::Ai, 'bad');
        $r2->resolveDeleted($manager);
        $this->assertTrue($c2->fresh()->is_deleted);
        $r2->refresh();
        $this->assertTrue($r2->moderated);
        $this->assertSame(ModerationAction::Deleted, $r2->moderation_action);
    }

    public function test_comment_moderation_stewardship_metrics_and_resource_scoping(): void
    {
        $circleA = $this->makeCircle();
        $circleB = $this->makeCircle();
        $groupA = $this->makeGroup($circleA);
        $groupB = $this->makeGroup($circleB);
        $dA = app(ForumService::class)->createDiscussion($groupA, User::factory()->create(), ['title' => 'A']);
        $dB = app(ForumService::class)->createDiscussion($groupB, User::factory()->create(), ['title' => 'B']);

        $cA = $dA->comments()->create(['user_id' => $this->member($circleA)->id, 'content' => 'a']);
        $cB = $dB->comments()->create(['user_id' => $this->member($circleB)->id, 'content' => 'b']);
        $recA = CommentModerationRecord::open($cA, ModerationFlagSource::Ai, 'x');
        $recA->update(['created_at' => now()->subDay()]);
        $recB = CommentModerationRecord::open($cB, ModerationFlagSource::User);

        // Per-circle metrics reach through comment → discussion → group → circle.
        $this->assertSame('Comment Moderation', CommentModerationRecord::queueLabel());
        $this->assertSame(1, CommentModerationRecord::pendingCountForCircle($circleA));
        $this->assertSame(1, CommentModerationRecord::pendingCountForCircle($circleB));
        $this->assertNotNull(CommentModerationRecord::oldestPendingAgeForCircle($circleA));

        // Resolved records drop out of the pending metrics.
        $recA->update(['moderated' => true]);
        $this->assertSame(0, CommentModerationRecord::pendingCountForCircle($circleA));
        $this->assertNull(CommentModerationRecord::oldestPendingAgeForCircle($circleA));

        // Resource query: a circle_admin of B sees only B's records.
        $this->actingAs($this->makeManager($circleB)->fresh());
        $this->assertSame(
            [$recB->id],
            CommentModerationRecordResource::getEloquentQuery()->pluck('id')->all(),
        );

        // Deep link carries the circle filter.
        $this->assertStringContainsString((string) $circleB->id, CommentModerationRecord::filamentUrlForCircle($circleB));
    }

    public function test_only_unresolved_ai_records_trigger_pending_review(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $c = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'hi']);

        // A user-sourced flag alone does NOT quarantine.
        CommentModerationRecord::open($c, ModerationFlagSource::User);
        $this->assertFalse($c->fresh()->pendingAiReview());

        // An unresolved AI record does.
        $ai = CommentModerationRecord::open($c, ModerationFlagSource::Ai, 'x');
        $this->assertTrue($c->fresh()->pendingAiReview());

        // Resolving it (e.g. admin Approve) reverts naturally — no unhide step.
        $ai->update(['moderated' => true]);
        $this->assertFalse($c->fresh()->pendingAiReview());
    }

    public function test_pending_ai_review_is_batched_not_n_plus_one(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);

        $flagged = collect();
        foreach (range(1, 3) as $i) {
            $c = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => "c{$i}"]);
            CommentModerationRecord::open($c, ModerationFlagSource::Ai, 'x');
            $flagged->push($c->id);
        }
        $clean = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'clean']);

        $this->actingAs($this->member($circle)->fresh());
        $page = $this->pageFor($circle, $group, $d);

        DB::enableQueryLog();
        $resp = $page->responses();
        $moderationQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'comment_moderation_records'))
            ->count();
        DB::disableQueryLog();

        // ONE query for the whole page regardless of comment count (no per-row).
        $this->assertSame(1, $moderationQueries);
        // Keyed [comment_id => record_id] so the badge can deep-link the record.
        $this->assertEqualsCanonicalizing($flagged->all(), array_keys($resp['pendingAiReview']));
        $this->assertArrayNotHasKey($clean->id, $resp['pendingAiReview']);
    }

    public function test_pending_ai_review_renders_three_ways_by_viewer(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);

        $author = $this->member($circle);
        $flagged = $d->comments()->create(['user_id' => $author->id, 'content' => 'SECRETFLAGGEDTEXT']);
        // A reply beneath it must stay visible to everyone (tombstone, not hide).
        $d->comments()->create(['user_id' => $this->member($circle)->id, 'parent_id' => $flagged->id, 'content' => 'VISIBLEREPLY']);
        CommentModerationRecord::open($flagged, ModerationFlagSource::Ai, 'bad words');

        $args = ['circle' => $circle, 'forumGroup' => $group, 'forumDiscussion' => $d];

        // 1. Author: full content + generic warning (never the AI reasoning).
        $this->actingAs($author->fresh());
        Livewire::test(ForumDiscussionPage::class, $args)
            ->assertSee('SECRETFLAGGEDTEXT')
            ->assertSee('awaiting review')
            ->assertDontSee('bad words')
            ->assertSee('VISIBLEREPLY');

        // 2. Moderator: full content + informational badge.
        $this->actingAs($this->makeManager($circle)->fresh());
        Livewire::test(ForumDiscussionPage::class, $args)
            ->assertSee('SECRETFLAGGEDTEXT')
            ->assertSee('Pending Review')
            ->assertSee('VISIBLEREPLY');

        // 3. Another participant: tombstone, no content; the reply still shows.
        $this->actingAs($this->member($circle)->fresh());
        Livewire::test(ForumDiscussionPage::class, $args)
            ->assertDontSee('SECRETFLAGGEDTEXT')
            ->assertSee('This comment is pending review')
            ->assertSee('VISIBLEREPLY');
    }

    public function test_participant_count_excludes_deleted_authors(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $a = User::factory()->create();
        $d = app(ForumService::class)->createDiscussion($group, $a, ['title' => 'T']); // creator A
        $b = User::factory()->create();
        $c = User::factory()->create();

        $rb = $d->comments()->create(['user_id' => $b->id, 'content' => 'B']);
        $rc = $d->comments()->create(['user_id' => $c->id, 'content' => 'C']);
        $this->assertSame(3, $d->participantCount()); // A, B, C

        // Hard-delete B's only comment → B drops out.
        $rb->deleteBy($b);
        $this->assertSame(2, $d->participantCount()); // A, C

        // C's comment gains a reply from B, then is tombstoned → C drops, B stays.
        $d->comments()->create(['user_id' => $b->id, 'parent_id' => $rc->id, 'content' => 'reply']);
        $this->assertSame(3, $d->participantCount()); // A, C, B
        $rc->deleteBy($c);
        $this->assertSame(2, $d->participantCount()); // A, B (C's only comment tombstoned)
    }

    public function test_poll_refresh_surfaces_new_comments_but_keeps_an_open_draft(): void
    {
        $circle = $this->makeCircle();
        $group = $this->makeGroup($circle);
        $d = app(ForumService::class)->createDiscussion($group, User::factory()->create(), ['title' => 'T']);
        $existing = $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'first comment']);
        $this->actingAs($this->member($circle)->fresh());

        $t = Livewire::test(ForumDiscussionPage::class, [
            'circle' => $circle,
            'forumGroup' => $group,
            'forumDiscussion' => $d,
        ]);

        // The poll is wired to the scoped action, not the root component.
        $t->assertSee('first comment')
            ->assertSeeHtml('wire:poll.10s="refreshComments"');

        // The viewer opens a reply composer and starts typing.
        $t->set('replyingToId', $existing->id)->set('replyContent', 'half-written reply');

        // Someone else posts while they're typing.
        $d->comments()->create(['user_id' => $this->member($circle)->id, 'content' => 'brand new comment']);

        // A poll tick fires: the new comment appears, the draft is untouched.
        $t->call('refreshComments')
            ->assertSee('brand new comment')
            ->assertSet('replyContent', 'half-written reply')
            ->assertSet('replyingToId', $existing->id);
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

        // A blocked post adds nobody; the count stays at the creator (1).
        $page->newRootContent = 'Nope';
        $page->postRoot(); // guarded no-op
        $this->assertSame(0, $d->comments()->count());
        $this->assertSame(1, $page->participantCount());
    }
}
