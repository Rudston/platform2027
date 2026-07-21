<?php

namespace Tests\Feature;

use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Forum discussion participation: join/leave following the CircleMembership
 * never-delete / close-via-left_at pattern.
 */
class ForumDiscussionParticipantTest extends TestCase
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
        (include database_path('migrations/2026_07_19_000001_create_forum_discussion_participants_table.php'))->up();
    }

    private function makeDiscussion(): ForumDiscussion
    {
        $circleId = DB::table('circles')->insertGetId(['name' => 'C']);
        $group = ForumGroup::create(['circle_id' => $circleId, 'name' => 'G', 'slug' => 'g']);

        return ForumDiscussion::create([
            'forum_group_id' => $group->id,
            'title' => 'T',
            'content' => 'c',
            'slug' => 't',
        ]);
    }

    public function test_join_is_idempotent_and_tracks_active_participants(): void
    {
        $discussion = $this->makeDiscussion();
        $user = User::factory()->create();

        $this->assertFalse($discussion->isJoinedBy($user));
        $this->assertCount(0, $discussion->activeParticipants());

        $discussion->join($user);
        $discussion->join($user); // idempotent — no second active row

        $this->assertTrue($discussion->isJoinedBy($user));
        $this->assertCount(1, $discussion->activeParticipants());
    }

    public function test_leave_closes_via_left_at_without_deleting(): void
    {
        $discussion = $this->makeDiscussion();
        $user = User::factory()->create();
        $discussion->join($user);

        $discussion->leave($user);

        $this->assertFalse($discussion->isJoinedBy($user));
        $this->assertCount(0, $discussion->activeParticipants());
        // Row is kept (closed), not deleted.
        $this->assertSame(1, $discussion->participants()->count());
        $this->assertNotNull($discussion->participants()->first()->left_at);
    }

    public function test_rejoin_after_leaving_opens_a_fresh_row(): void
    {
        $discussion = $this->makeDiscussion();
        $user = User::factory()->create();

        $discussion->join($user);
        $discussion->leave($user);
        $discussion->join($user);

        $this->assertTrue($discussion->isJoinedBy($user));
        $this->assertCount(1, $discussion->activeParticipants());   // one active
        $this->assertSame(2, $discussion->participants()->count()); // two rows (history kept)
    }

    public function test_guest_is_never_joined(): void
    {
        $discussion = $this->makeDiscussion();
        $this->assertFalse($discussion->isJoinedBy(null));
    }
}
