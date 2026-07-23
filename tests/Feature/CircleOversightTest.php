<?php

namespace Tests\Feature;

use App\Enums\Moderation\ModerationFlagSource;
use App\Livewire\Communities\CircleOversightPage;
use App\Models\Circles\Circle;
use App\Models\Comment;
use App\Models\Communication\Request;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Moderation\CommentModerationRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Per-circle stewardship Oversight page: platform-admin-only access, and rows
 * driven by the config/stewardship.php registry with a neglect highlight.
 */
class CircleOversightTest extends TestCase
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

        (include database_path('migrations/2026_07_03_124004_create_content_blocks_table.php'))->up();
        (include database_path('migrations/2026_07_07_000001_add_collapsible_to_content_blocks_table.php'))->up();
        (include database_path('migrations/2026_07_07_000003_create_requests_table.php'))->up();
        (include database_path('migrations/2026_07_14_000001_add_responsible_admin_id_to_requests_table.php'))->up();
        (include database_path('migrations/2026_07_16_000002_create_forum_groups_table.php'))->up();
        (include database_path('migrations/2026_07_16_000003_create_forum_discussions_table.php'))->up();
        (include database_path('migrations/2026_07_21_000002_create_comments_table.php'))->up();
        (include database_path('migrations/2026_07_21_000005_add_delete_edit_columns_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_22_000001_add_moderation_columns_to_comments_table.php'))->up();
        (include database_path('migrations/2026_07_22_000002_create_comment_moderation_records_table.php'))->up();
        (include database_path('migrations/2026_07_23_000001_add_snapshot_columns_to_comment_moderation_records_table.php'))->up();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function makeCircle(): Circle
    {
        $id = DB::table('circles')->insertGetId(['name' => 'C']);

        return Circle::find($id);
    }

    private function ensureRole(string $name): int
    {
        return DB::table('roles')->where('name', $name)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => $name, 'guard_name' => 'web', 'circle_id' => null]);
    }

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        DB::table('model_has_roles')->insert([
            'role_id' => $this->ensureRole('admin'),
            'model_type' => User::class,
            'model_id' => $user->id,
            'circle_id' => null,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function circleAdmin(Circle $circle): User
    {
        $user = User::factory()->create();
        DB::table('model_has_roles')->insert([
            'role_id' => $this->ensureRole('circle_admin'),
            'model_type' => User::class,
            'model_id' => $user->id,
            'circle_id' => $circle->id,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function forumComment(Circle $circle): Comment
    {
        $group = ForumGroup::create(['circle_id' => $circle->id, 'name' => 'G'.uniqid(), 'slug' => 'g'.uniqid(), 'visibility' => 'public']);
        $d = ForumDiscussion::create(['forum_group_id' => $group->id, 'title' => 'T', 'content' => 'c', 'slug' => 't'.uniqid()]);

        return $d->comments()->create(['user_id' => User::factory()->create()->id, 'content' => 'x']);
    }

    public function test_oversight_page_is_platform_admin_only(): void
    {
        $circle = $this->makeCircle();

        // Guest.
        Livewire::test(CircleOversightPage::class, ['circle' => $circle])->assertStatus(403);

        // Ordinary member.
        $this->actingAs(User::factory()->create());
        Livewire::test(CircleOversightPage::class, ['circle' => $circle])->assertStatus(403);

        // circle_admin — explicitly forbidden; this page is the layer above them.
        $this->actingAs($this->circleAdmin($circle));
        Livewire::test(CircleOversightPage::class, ['circle' => $circle])->assertStatus(403);

        // Platform admin.
        $this->actingAs($this->platformAdmin());
        Livewire::test(CircleOversightPage::class, ['circle' => $circle])->assertOk();
    }

    public function test_rows_show_counts_and_flag_neglected_queues(): void
    {
        $circle = $this->makeCircle();

        // An old pending request (older than the default 7-day threshold).
        $req = Request::create([
            'type' => 'organisation_approval',
            'status' => 'pending',
            'direction' => 'external',
            'requester_id' => User::factory()->create()->id,
            'circle_id' => $circle->id,
        ]);
        // created_at isn't in Request's $fillable — forceFill past mass assignment.
        $req->forceFill(['created_at' => now()->subDays(10)])->save();

        // A fresh comment-moderation record (well within threshold).
        CommentModerationRecord::open($this->forumComment($circle), ModerationFlagSource::Ai, 'flagged');

        $this->actingAs($this->platformAdmin());

        $rows = Livewire::test(CircleOversightPage::class, ['circle' => $circle])
            ->assertOk()
            ->assertSee('Pending Requests')
            ->assertSee('Comment Moderation')
            ->assertSee('Overdue') // the neglected request row is highlighted
            ->instance()
            ->queues();

        $byLabel = collect($rows)->keyBy('label');

        $this->assertSame(1, $byLabel['Pending Requests']['count']);
        $this->assertTrue($byLabel['Pending Requests']['neglected']);

        $this->assertSame(1, $byLabel['Comment Moderation']['count']);
        $this->assertFalse($byLabel['Comment Moderation']['neglected']);
    }
}
