<?php

namespace Tests\Feature;

use App\Contracts\Moderation\CommentModerationCheckerContract;
use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationFlagSource;
use App\Models\Comment;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Moderation\CommentModerationRecord;
use App\Models\User;
use App\Support\Moderation\CommentableTypeLabeler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unified comment moderation — schema, the stub checker, the create-or-reuse
 * record dedupe, the checking console command, and the edit-invalidation hook.
 * (Filament dashboard + front-end Hide are a later step.)
 */
class CommentModerationTest extends TestCase
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
        (include database_path('migrations/2026_07_21_000005_add_delete_edit_columns_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_23_000002_add_last_edited_by_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_22_000001_add_moderation_columns_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_22_000002_create_comment_moderation_records_table.php'))->up();
        (include database_path('migrations/2026_07_23_000001_add_snapshot_columns_to_comment_moderation_records_table.php'))->up();
        (include database_path('migrations/2026_07_24_000001_add_forum_group_visibility_to_comment_moderation_records_table.php'))->up();

        config()->set('moderation.trigger_words', ['moderationtestflag']);
    }

    private function makeDiscussion(): ForumDiscussion
    {
        $circleId = DB::table('circles')->insertGetId(['name' => 'C']);
        $group = ForumGroup::create(['circle_id' => $circleId, 'name' => 'G', 'slug' => 'g']);

        return ForumDiscussion::create(['forum_group_id' => $group->id, 'title' => 'T', 'content' => 'c', 'slug' => 't']);
    }

    private function makeComment(ForumDiscussion $d, ?User $user = null, array $attrs = []): Comment
    {
        return $d->comments()->create(array_merge([
            'user_id' => ($user ?? User::factory()->create())->id,
            'content' => 'hello world',
        ], $attrs));
    }

    public function test_commentable_type_labeler(): void
    {
        $this->assertSame('Forum Discussion', CommentableTypeLabeler::label(ForumDiscussion::class));
        $this->assertSame('Comment', CommentableTypeLabeler::label(null));
        $this->assertSame('Comment', CommentableTypeLabeler::label('App\\Models\\Something'));
    }

    public function test_open_snapshots_circle_type_label_and_url(): void
    {
        $d = $this->makeDiscussion();
        $record = CommentModerationRecord::open($this->makeComment($d), ModerationFlagSource::Ai, 'x');

        $this->assertSame($d->group->circle_id, $record->circle_id);
        $this->assertSame('Forum Discussion', $record->commentable_type_label);
        $this->assertNotNull($record->url_to_parent);
        $this->assertStringContainsString('/forums/', $record->url_to_parent);
    }

    public function test_recheck_auto_resolves_after_author_fix(): void
    {
        config()->set('moderation.trigger_words', ['moderationtestflag']);
        $d = $this->makeDiscussion();
        $author = User::factory()->create();
        $comment = $this->makeComment($d, $author, ['content' => 'moderationtestflag here']);

        // First check flags it → pending.
        $this->artisan('comments:check-moderation')->assertSuccessful();
        $record = $comment->moderationRecords()->first();
        $this->assertNotNull($record);
        $this->assertFalse($record->moderated);
        $this->assertTrue($comment->fresh()->pendingAiReview());

        // Author fixes it (edit sets fixed_by_author + moderated_content, nulls ai_checked_at).
        $comment->fresh()->editBy($author, 'all clean now');
        $record->refresh();
        $this->assertTrue($record->fixed_by_author);
        $this->assertSame('all clean now', $record->moderated_content);
        $this->assertNull($comment->fresh()->ai_checked_at);
        // The author's own edit stamps last_edited_by_user_id = their own id.
        $this->assertSame($author->id, $comment->fresh()->last_edited_by_user_id);

        // Recheck comes back clean → auto-resolve.
        $this->artisan('comments:check-moderation')->assertSuccessful();
        $record->refresh();
        $this->assertTrue($record->moderated);
        $this->assertTrue($record->moderated_as_ok);
        $this->assertSame(ModerationAction::Approved, $record->moderation_action);
        $this->assertNull($record->moderated_by_user_id);            // system-resolved, not a human
        $this->assertTrue($record->fixed_by_author);                 // untouched
        $this->assertSame('all clean now', $record->moderated_content); // untouched
        $this->assertFalse($comment->fresh()->pendingAiReview());    // renders normally again
    }

    public function test_resolve_edited_and_approved_updates_comment_and_record(): void
    {
        config()->set('moderation.trigger_words', ['moderationtestflag']);
        $d = $this->makeDiscussion();
        $author = User::factory()->create();
        $admin = User::factory()->create();
        $comment = $this->makeComment($d, $author, ['content' => 'moderationtestflag bad']);

        $this->artisan('comments:check-moderation');
        $record = $comment->moderationRecords()->first();
        $checkedAt = $comment->fresh()->ai_checked_at;
        $this->assertNotNull($checkedAt);

        $record->resolveEditedAndApproved($admin, 'cleaned by admin');

        $comment->refresh();
        $this->assertSame('cleaned by admin', $comment->content);
        $this->assertNotNull($comment->edited_at);
        $this->assertSame($admin->id, $comment->last_edited_by_user_id);
        // Deliberately NOT requeued for a recheck — a human approved this wording.
        $this->assertNotNull($comment->ai_checked_at);
        $this->assertSame($checkedAt->timestamp, $comment->ai_checked_at->timestamp);

        $record->refresh();
        $this->assertTrue($record->moderated);
        $this->assertTrue($record->moderated_as_ok);
        $this->assertSame(ModerationAction::EditedAndApproved, $record->moderation_action);
        $this->assertSame($admin->id, $record->moderated_by_user_id);
        $this->assertSame('cleaned by admin', $record->moderated_content);
    }

    public function test_recheck_still_offensive_stays_pending_without_duplicate(): void
    {
        config()->set('moderation.trigger_words', ['moderationtestflag']);
        $d = $this->makeDiscussion();
        $author = User::factory()->create();
        $comment = $this->makeComment($d, $author, ['content' => 'moderationtestflag one']);

        $this->artisan('comments:check-moderation');
        $record = $comment->moderationRecords()->first();

        // Author edits but the trigger is still present.
        $comment->fresh()->editBy($author, 'moderationtestflag still');
        $this->assertTrue($record->fresh()->fixed_by_author);

        // Recheck still offensive → stays pending, no auto-resolve, no duplicate.
        $this->artisan('comments:check-moderation');
        $this->assertSame(1, $comment->moderationRecords()->count());
        $record->refresh();
        $this->assertFalse($record->moderated);
        $this->assertTrue($record->fixed_by_author);
    }

    public function test_first_time_clean_check_does_nothing(): void
    {
        $d = $this->makeDiscussion();
        $this->makeComment($d, null, ['content' => 'perfectly fine']);

        $this->artisan('comments:check-moderation')->assertSuccessful();

        $this->assertSame(0, CommentModerationRecord::count());
    }

    public function test_stub_checker_flags_trigger_words_only(): void
    {
        $checker = app(CommentModerationCheckerContract::class);

        $clean = $checker->check('a perfectly civil comment');
        $this->assertFalse($clean->containsOffensiveContent);
        $this->assertNull($clean->message);

        $dirty = $checker->check('this contains MODERATIONTESTFLAG in caps');
        $this->assertTrue($dirty->containsOffensiveContent);
        $this->assertNotNull($dirty->message);
    }

    public function test_open_dedupes_per_source_but_ai_and_user_coexist(): void
    {
        $d = $this->makeDiscussion();
        $comment = $this->makeComment($d);

        $ai1 = CommentModerationRecord::open($comment, ModerationFlagSource::Ai, 'first');
        $ai2 = CommentModerationRecord::open($comment, ModerationFlagSource::Ai, 'second');
        $this->assertTrue($ai1->is($ai2)); // reused, not duplicated
        $this->assertSame('first', $ai2->ai_message); // original snapshot kept

        // A user flag on the same comment is a separate, coexisting row.
        $user = CommentModerationRecord::open($comment, ModerationFlagSource::User);
        $this->assertFalse($ai1->is($user));
        $this->assertSame(2, $comment->moderationRecords()->count());

        // Once resolved, a fresh flag opens a NEW pending record.
        $ai1->update(['moderated' => true]);
        $ai3 = CommentModerationRecord::open($comment, ModerationFlagSource::Ai);
        $this->assertFalse($ai1->is($ai3));
    }

    public function test_command_checks_flags_and_is_idempotent(): void
    {
        $d = $this->makeDiscussion();
        $clean = $this->makeComment($d, null, ['content' => 'nothing wrong here']);
        $offensive = $this->makeComment($d, null, ['content' => 'moderationtestflag right here']);
        $deleted = $this->makeComment($d, null, ['content' => 'moderationtestflag but deleted', 'is_deleted' => true]);

        $this->artisan('comments:check-moderation')->assertSuccessful();

        // Both non-deleted comments were checked; the deleted one was skipped.
        $this->assertNotNull($clean->fresh()->ai_checked_at);
        $this->assertNotNull($offensive->fresh()->ai_checked_at);
        $this->assertNull($deleted->fresh()->ai_checked_at);

        // Only the offensive one produced a pending AI record.
        $this->assertSame(0, $clean->moderationRecords()->count());
        $this->assertSame(1, $offensive->moderationRecords()->count());
        $record = $offensive->moderationRecords()->first();
        $this->assertSame(ModerationFlagSource::Ai, $record->flagged_by);
        $this->assertSame('moderationtestflag right here', $record->content);
        $this->assertNotNull($record->ai_message);

        // Re-running checks nothing new and creates no duplicate record.
        $this->artisan('comments:check-moderation')->assertSuccessful();
        $this->assertSame(1, $offensive->moderationRecords()->count());
    }

    public function test_edit_invalidates_ai_check_and_marks_pending_record_fixed(): void
    {
        $d = $this->makeDiscussion();
        $author = User::factory()->create();
        $comment = $this->makeComment($d, $author, ['content' => 'moderationtestflag original']);

        $this->artisan('comments:check-moderation')->assertSuccessful();
        $record = $comment->moderationRecords()->first();
        $this->assertNotNull($comment->fresh()->ai_checked_at);
        $this->assertNotNull($record);

        // Author edits → AI check invalidated, pending record marked fixed with
        // the new content, but NOT auto-resolved.
        $comment->fresh()->editBy($author, 'cleaned up version');

        $this->assertNull($comment->fresh()->ai_checked_at);
        $record->refresh();
        $this->assertTrue($record->fixed_by_author);
        $this->assertSame('cleaned up version', $record->moderated_content);
        $this->assertSame('moderationtestflag original', $record->content); // original snapshot untouched
        $this->assertFalse($record->moderated); // admin still decides
    }

    public function test_resolve_approved_marks_the_record_ok_and_leaves_the_comment(): void
    {
        $d = $this->makeDiscussion();
        $comment = $this->makeComment($d);
        $record = CommentModerationRecord::open($comment, ModerationFlagSource::Ai, 'flagged');

        $admin = User::factory()->create();
        $record->resolveApproved($admin);

        $record->refresh();
        $this->assertTrue($record->moderated);
        $this->assertTrue($record->moderated_as_ok);
        $this->assertSame(ModerationAction::Approved, $record->moderation_action);
        $this->assertSame($admin->id, $record->moderated_by_user_id);

        // Comment untouched by an approval.
        $this->assertFalse($comment->fresh()->hidden);
        $this->assertFalse($comment->fresh()->is_deleted);
    }
}
