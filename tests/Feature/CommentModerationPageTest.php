<?php

namespace Tests\Feature;

use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationFlagSource;
use App\Filament\Resources\CommentModerationRecords\Pages\ViewCommentModerationRecord;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Moderation\CommentModerationRecord;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The moderation record's Filament VIEW page — the page the front-end "Pending
 * Review" badge links to. Confirms the Approve / Edit & Approve / Hide / Delete
 * actions are present and act on the record from THIS page (the original bug was
 * that the view page had no actions).
 */
class CommentModerationPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();

        Schema::create('circles', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('status')->default('active');
            $t->softDeletes();
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

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $roleId = DB::table('roles')->where('name', 'admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web', 'circle_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id, 'circle_id' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function pendingRecord(string $content): CommentModerationRecord
    {
        $circleId = DB::table('circles')->insertGetId(['name' => 'C']);
        $group = ForumGroup::create(['circle_id' => $circleId, 'name' => 'G'.uniqid(), 'slug' => 'g'.uniqid(), 'visibility' => 'public']);
        $d = ForumDiscussion::create(['forum_group_id' => $group->id, 'created_by' => User::factory()->create()->id, 'title' => 'T', 'content' => 'c', 'slug' => 't'.uniqid()]);
        $comment = $d->comments()->create(['user_id' => User::factory()->create()->id, 'content' => $content]);

        return CommentModerationRecord::open($comment, ModerationFlagSource::Ai, 'flagged');
    }

    public function test_view_page_exposes_the_actions_and_approve_resolves(): void
    {
        $record = $this->pendingRecord('some flagged text');
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(ViewCommentModerationRecord::class, ['record' => $record->getRouteKey()])
            ->assertActionVisible('approve')
            ->assertActionVisible('editAndApprove')
            ->assertActionVisible('hide')
            ->assertActionVisible('delete')
            ->callAction('approve')
            ->assertActionHidden('approve'); // resolved → actions gone

        $record->refresh();
        $this->assertTrue($record->moderated);
        $this->assertSame(ModerationAction::Approved, $record->moderation_action);
        $this->assertSame($admin->id, $record->moderated_by_user_id);
    }

    public function test_view_page_edit_and_approve(): void
    {
        $record = $this->pendingRecord('original bad text');
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(ViewCommentModerationRecord::class, ['record' => $record->getRouteKey()])
            ->callAction('editAndApprove', ['content' => 'admin fixed wording']);

        $record->refresh();
        $this->assertTrue($record->moderated);
        $this->assertSame(ModerationAction::EditedAndApproved, $record->moderation_action);
        $this->assertSame($admin->id, $record->moderated_by_user_id);
        $this->assertSame('admin fixed wording', $record->moderated_content);

        $comment = $record->comment;
        $this->assertSame('admin fixed wording', $comment->content);
        $this->assertSame($admin->id, $comment->last_edited_by_user_id);
    }
}
