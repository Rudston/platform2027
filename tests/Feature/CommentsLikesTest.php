<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Like;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * Comments (self-nesting reply engine) & Likes — models/relations only.
 */
class CommentsLikesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();

        Schema::create('circles', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        (include database_path('migrations/2026_07_16_000002_create_forum_groups_table.php'))->up();
        (include database_path('migrations/2026_07_16_000003_create_forum_discussions_table.php'))->up();
        (include database_path('migrations/2026_07_21_000002_create_comments_table.php'))->up();
        (include database_path('migrations/2026_07_21_000003_create_likes_table.php'))->up();
    }

    private function makeDiscussion(): ForumDiscussion
    {
        $circleId = DB::table('circles')->insertGetId(['name' => 'C']);
        $group = ForumGroup::create(['circle_id' => $circleId, 'name' => 'G', 'slug' => 'g']);

        return ForumDiscussion::create(['forum_group_id' => $group->id, 'title' => 'T', 'content' => 'c', 'slug' => 't']);
    }

    private function makeComment(ForumDiscussion $d, User $user, ?Comment $parent = null, array $attrs = []): Comment
    {
        return $d->comments()->create(array_merge([
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'content' => 'hi',
        ], $attrs));
    }

    public function test_root_comment_and_replies(): void
    {
        $d = $this->makeDiscussion();
        $user = User::factory()->create();

        $root = $this->makeComment($d, $user);
        $reply = $this->makeComment($d, $user, $root);

        $this->assertTrue($root->isRoot());
        $this->assertFalse($reply->fresh()->isRoot());
        $this->assertSame($root->id, $reply->parent->id);
        $this->assertEqualsCanonicalizing([$reply->id], $root->replies()->pluck('id')->all());
        $this->assertSame($user->id, $root->user->id);
    }

    public function test_posts_alias_returns_the_same_rows_as_comments(): void
    {
        $d = $this->makeDiscussion();
        $user = User::factory()->create();
        $this->makeComment($d, $user);
        $this->makeComment($d, $user);

        $this->assertEqualsCanonicalizing(
            $d->comments()->pluck('id')->all(),
            $d->posts()->pluck('id')->all(),
        );
        $this->assertSame(2, $d->posts()->count());
    }

    public function test_a_root_comment_can_be_pinned(): void
    {
        $d = $this->makeDiscussion();
        $root = $this->makeComment($d, User::factory()->create(), null, ['pinned' => true, 'pinned_position' => 1]);

        $this->assertTrue($root->fresh()->pinned);
    }

    public function test_pinning_a_reply_throws(): void
    {
        $d = $this->makeDiscussion();
        $user = User::factory()->create();
        $root = $this->makeComment($d, $user);

        $this->expectException(LogicException::class);
        $this->makeComment($d, $user, $root, ['pinned' => true]);
    }

    public function test_liking_a_comment_and_uniqueness(): void
    {
        $d = $this->makeDiscussion();
        $user = User::factory()->create();
        $comment = $this->makeComment($d, $user);

        $like = $comment->likes()->create(['user_id' => $user->id]);
        $this->assertSame(1, $comment->likes()->count());
        $this->assertSame($user->id, $like->user->id);
        $this->assertTrue($comment->is($like->likeable));

        // Same user liking the same comment again violates the unique index.
        $this->expectException(QueryException::class);
        $comment->likes()->create(['user_id' => $user->id]);
    }

    public function test_discussion_itself_is_likeable(): void
    {
        $d = $this->makeDiscussion();
        $user = User::factory()->create();

        $d->likes()->create(['user_id' => $user->id]);

        $this->assertSame(1, $d->likes()->count());
        $this->assertSame(Like::class, $d->likes()->first()::class);
    }
}
