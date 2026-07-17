<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use App\Models\Communities\ThemeCommunity;
use App\Models\Theme;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * circles:backfill-theme-tags — tags each ThemeCommunity's circle with its own
 * theme; idempotent; leaves non-ThemeCommunity circles alone.
 */
class BackfillThemeCommunityTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('themes', function ($t): void {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->timestamps();
        });
        Schema::create('theme_communities', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->unsignedBigInteger('theme_id')->nullable();
            $t->softDeletes();
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
        (include database_path('migrations/2026_07_17_000001_create_taggables_table.php'))->up();
    }

    private function makeThemeCircle(Theme $theme): Circle
    {
        $tc = ThemeCommunity::create(['name' => $theme->name, 'theme_id' => $theme->id]);
        $id = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::ThemeCommunity->value,
            'circleable_id' => $tc->id,
            'name' => $theme->name,
        ]);

        return Circle::find($id);
    }

    public function test_it_tags_theme_community_circles_idempotently(): void
    {
        $health = Theme::create(['name' => 'Health']);
        $circle = $this->makeThemeCircle($health);

        // A non-ThemeCommunity circle must be left untouched.
        $otherId = DB::table('circles')->insertGetId(['circleable_type' => CommunityType::Campaign->value, 'name' => 'X']);

        Artisan::call('circles:backfill-theme-tags');
        $this->assertStringContainsString('1 theme-community circles tagged', Artisan::output());
        $this->assertSame(['Health'], $circle->fresh()->tags()->pluck('name')->all());
        $this->assertSame(0, Circle::find($otherId)->tags()->count());

        // Re-run: nothing new.
        Artisan::call('circles:backfill-theme-tags');
        $this->assertStringContainsString('0 theme-community circles tagged', Artisan::output());
        $this->assertSame(1, $circle->fresh()->tags()->count());
    }
}
