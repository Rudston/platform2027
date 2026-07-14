<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Enums\LocatableType;
use App\Models\Circles\Circle;
use App\Services\Circles\CircleCreationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CircleCreationService inherits the parent circle's locatable when no explicit
 * location is given (the Explore "Add community" flow) — so a new community is
 * anchored to the location it was added under, not the Country default.
 *
 * The Organisation path never loads the locatable model (only ThemeCommunity
 * does), so no demography tables are needed. The full migration set can't run
 * on sqlite, so we hand-build just the tables this exercises.
 */
class CircleCreationLocatableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildCirclesTable();

        Schema::create('organisation_communities', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Empty services table: defaultServices() resolves to no ids, so nothing
        // is attached — but the query still runs, so the table must exist.
        Schema::create('services', function ($table): void {
            $table->id();
            $table->string('key');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    private function buildCirclesTable(): void
    {
        Schema::create('circles', function ($table): void {
            $table->id();
            $table->string('circleable_type')->nullable();
            $table->unsignedBigInteger('circleable_id')->nullable();
            $table->string('locatable_type')->nullable();
            $table->unsignedBigInteger('locatable_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('path')->nullable();
            $table->string('name')->nullable();
            $table->json('description')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_test')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_it_inherits_the_parent_locatable_when_none_is_given(): void
    {
        // A location circle anchored to a MainPlace (deep in the hierarchy).
        $parentId = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'locatable_type' => LocatableType::MainPlace->value,
            'locatable_id' => 242,
            'depth' => 0,
        ]);
        DB::table('circles')->where('id', $parentId)->update(['path' => (string) $parentId]);

        $parent = Circle::find($parentId);

        // Add an Organisation community under it WITHOUT specifying a location.
        $circle = app(CircleCreationService::class)->create(
            type: CommunityType::Organisation,
            data: ['name' => 'Wilderness Environmental Forum'],
            parentCircle: $parent,
        );

        // It anchors to the parent's MainPlace, not the Country default (#191).
        $this->assertSame(LocatableType::MainPlace->value, $circle->locatable_type);
        $this->assertSame(242, (int) $circle->locatable_id);
    }

    public function test_it_still_defaults_to_country_when_there_is_no_parent(): void
    {
        $circle = app(CircleCreationService::class)->create(
            type: CommunityType::Organisation,
            data: ['name' => 'National Body'],
        );

        $this->assertSame(LocatableType::Country->value, $circle->locatable_type);
        $this->assertSame(191, (int) $circle->locatable_id);
    }
}
