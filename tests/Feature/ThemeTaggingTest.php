<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\ThemeSuggestionStatus;
use App\Livewire\Tags\TagPicker;
use App\Models\Circles\Circle;
use App\Models\Forums\ForumDiscussion;
use App\Models\Forums\ForumGroup;
use App\Models\Theme;
use App\Models\ThemeSuggestion;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Theme-based tagging: the HasTags trait + Theme inverses, per-entity tagging
 * authorization, the TagPicker (attach/detach/suggest), and the
 * ThemeSuggestion approve/reject workflow (dedupe + origin auto-attach).
 */
class ThemeTaggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();
        (include database_path('migrations/2026_07_06_000001_create_email_templates_table.php'))->up();

        Schema::create('themes', function ($t): void {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->timestamps();
        });

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

        Schema::create('theme_communities', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->unsignedBigInteger('theme_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        (include database_path('migrations/2026_07_16_000002_create_forum_groups_table.php'))->up();
        (include database_path('migrations/2026_07_16_000003_create_forum_discussions_table.php'))->up();
        (include database_path('migrations/2026_07_17_000001_create_taggables_table.php'))->up();
        (include database_path('migrations/2026_07_17_000002_create_theme_suggestions_table.php'))->up();

        $this->seed(EmailTemplateSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function makeCircle(): Circle
    {
        $id = DB::table('circles')->insertGetId(['circleable_type' => CommunityType::LocationCommunity->value, 'name' => 'C']);

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

    public function test_theme_community_circle_is_auto_tagged_on_creation(): void
    {
        $theme = Theme::create(['name' => 'Water']);
        $tc = \App\Models\Communities\ThemeCommunity::create(['name' => 'Water', 'theme_id' => $theme->id]);

        // Created via Eloquent so the Circle::booted() created hook fires.
        $circle = Circle::create([
            'circleable_type' => CommunityType::ThemeCommunity->value,
            'circleable_id' => $tc->id,
            'name' => 'Water',
        ]);

        $this->assertSame(['Water'], $circle->tags()->pluck('name')->all());
    }

    public function test_tags_relation_and_theme_inverses(): void
    {
        $circle = $this->makeCircle();
        $theme = Theme::create(['name' => 'Water']);

        $circle->tags()->attach($theme->id);

        $this->assertSame(['Water'], $circle->tags()->pluck('name')->all());
        $this->assertTrue($theme->circles()->whereKey($circle->id)->exists());
    }

    public function test_forum_discussion_tagging_auth(): void
    {
        $circle = $this->makeCircle();
        $group = ForumGroup::create(['circle_id' => $circle->id, 'name' => 'G', 'slug' => 'g']);
        $author = User::factory()->create();
        $discussion = ForumDiscussion::create(['forum_group_id' => $group->id, 'created_by' => $author->id, 'title' => 'T', 'content' => 'c']);

        $circleAdmin = User::factory()->create();
        $this->grantCircleAdmin($circleAdmin, $circle->id);
        $stranger = User::factory()->create();

        $this->assertTrue($discussion->canBeTaggedBy($author->fresh()));        // author
        $this->assertTrue($discussion->canBeTaggedBy($circleAdmin->fresh()));   // circle manager
        $this->assertFalse($discussion->canBeTaggedBy($stranger->fresh()));     // neither
        $this->assertFalse($discussion->canBeTaggedBy(null));
    }

    public function test_forum_group_and_circle_tagging_auth(): void
    {
        $circle = $this->makeCircle();
        $group = ForumGroup::create(['circle_id' => $circle->id, 'name' => 'G', 'slug' => 'g']);

        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $regular = User::factory()->create();

        $this->assertTrue($group->canBeTaggedBy($admin->fresh()));
        $this->assertTrue($circle->canBeTaggedBy($admin->fresh()));
        $this->assertFalse($group->canBeTaggedBy($regular->fresh()));
        $this->assertFalse($circle->canBeTaggedBy($regular->fresh()));
    }

    public function test_picker_attach_detach_gated_to_managers(): void
    {
        $circle = $this->makeCircle();
        $theme = Theme::create(['name' => 'Housing']);
        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');
        $this->actingAs($admin->fresh());

        Livewire::test(TagPicker::class, ['taggableType' => Circle::class, 'taggableId' => $circle->id])
            ->call('attach', $theme->id);
        $this->assertTrue($circle->tags()->whereKey($theme->id)->exists());

        Livewire::test(TagPicker::class, ['taggableType' => Circle::class, 'taggableId' => $circle->id])
            ->call('detach', $theme->id);
        $this->assertFalse($circle->tags()->whereKey($theme->id)->exists());

        // A regular user cannot attach.
        $circle->tags()->detach();
        $this->actingAs(User::factory()->create());
        Livewire::test(TagPicker::class, ['taggableType' => Circle::class, 'taggableId' => $circle->id])
            ->call('attach', $theme->id)
            ->assertStatus(403);
    }

    public function test_any_authenticated_user_can_suggest_a_tag(): void
    {
        $circle = $this->makeCircle();
        $user = User::factory()->create(); // no manage rights
        $this->actingAs($user);

        Livewire::test(TagPicker::class, ['taggableType' => Circle::class, 'taggableId' => $circle->id])
            ->set('suggestName', 'Sanitation')
            ->call('submitSuggestion')
            ->assertDispatched('tag-suggested');

        $suggestion = ThemeSuggestion::first();
        $this->assertSame('Sanitation', $suggestion->name);
        $this->assertSame($user->id, $suggestion->requested_by);
        $this->assertSame(Circle::class, $suggestion->origin_taggable_type);
        $this->assertSame($circle->id, (int) $suggestion->origin_taggable_id);
        $this->assertSame(ThemeSuggestionStatus::Pending, $suggestion->status);
        // Suggesting attaches nothing.
        $this->assertSame(0, $circle->tags()->count());
    }

    public function test_approve_creates_theme_and_auto_attaches_to_origin(): void
    {
        $circle = $this->makeCircle();
        $requester = User::factory()->create();
        $reviewer = User::factory()->create();

        $suggestion = ThemeSuggestion::create([
            'name' => 'Roads',
            'requested_by' => $requester->id,
            'origin_taggable_type' => Circle::class,
            'origin_taggable_id' => $circle->id,
        ]);

        $theme = $suggestion->approve($reviewer);

        $this->assertSame('roads', $theme->slug);
        $this->assertSame(ThemeSuggestionStatus::Approved, $suggestion->fresh()->status);
        $this->assertSame($reviewer->id, $suggestion->fresh()->reviewed_by);
        $this->assertTrue($circle->tags()->whereKey($theme->id)->exists()); // auto-attached
    }

    public function test_approve_dedupes_an_existing_theme(): void
    {
        $existing = Theme::create(['name' => 'Energy']);
        $suggestion = ThemeSuggestion::create(['name' => 'Energy', 'requested_by' => User::factory()->create()->id]);

        $theme = $suggestion->approve(User::factory()->create());

        $this->assertSame($existing->id, $theme->id);         // reused, not duplicated
        $this->assertSame(1, Theme::where('slug', 'energy')->count());
    }

    public function test_tag_list_component_renders_alphabetically(): void
    {
        $circle = $this->makeCircle();
        $circle->tags()->attach(Theme::create(['name' => 'Zebra'])->id);
        $circle->tags()->attach(Theme::create(['name' => 'Apple'])->id);

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-tag-list :tags="$tags" />',
            ['tags' => $circle->tags()->get()],
        );

        // Apple appears before Zebra regardless of attach order.
        $this->assertLessThan(strpos($html, 'Zebra'), strpos($html, 'Apple'));
    }

    public function test_community_page_tags_computeds_gate_editing(): void
    {
        $circle = $this->makeCircle();
        $circle->tags()->attach(Theme::create(['name' => 'Water'])->id);

        $admin = User::factory()->create();
        $this->grantGlobalRole($admin, 'admin');

        // The read-only tag row is available to everyone; only managers get the
        // canManageTags flag that reveals the Edit-tags affordance.
        $page = new \App\Livewire\Communities\CommunityPage;
        $page->circle = $circle;
        $this->assertSame(['Water'], $page->tags()->pluck('name')->all());

        $this->actingAs($admin->fresh());
        $this->assertTrue($page->canManageTags());

        $this->actingAs(User::factory()->create());
        $this->assertFalse($page->canManageTags());
    }

    public function test_reject_records_note_and_does_not_create_theme(): void
    {
        $suggestion = ThemeSuggestion::create(['name' => 'Spam Tag', 'requested_by' => User::factory()->create()->id]);

        $suggestion->reject(User::factory()->create(), 'Not a real category');

        $this->assertSame(ThemeSuggestionStatus::Rejected, $suggestion->fresh()->status);
        $this->assertSame('Not a real category', $suggestion->fresh()->review_note);
        $this->assertSame(0, Theme::where('slug', 'spam-tag')->count());
    }
}
