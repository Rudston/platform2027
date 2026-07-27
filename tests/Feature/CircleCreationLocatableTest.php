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

        // Needed only by the LocationCommunity case (the root country circle).
        Schema::create('location_communities', function ($table): void {
            $table->id();
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

    /**
     * The Explore flow represents the national level as *no* selected circle, so
     * a country-level organisation arrives with parentCircle = null. It must
     * still nest under the country circle rather than become a second root.
     */
    public function test_a_parentless_community_nests_under_its_location_circle(): void
    {
        $countryCircleId = $this->insertCountryCircle();

        $circle = app(CircleCreationService::class)->create(
            type: CommunityType::Organisation,
            data: ['name' => 'National Body'],
        );

        $this->assertSame($countryCircleId, (int) $circle->parent_id);
        $this->assertSame(1, $circle->depth);
        $this->assertSame($countryCircleId.'/'.$circle->id, $circle->path);
    }

    /**
     * The country circle is itself created through this service with no parent
     * (LocationCommunitiesSeeder), so LocationCommunity must never have a parent
     * derived for it — the root has to stay parentless.
     */
    public function test_a_location_community_never_has_a_parent_derived(): void
    {
        $this->insertCountryCircle();

        $circle = app(CircleCreationService::class)->create(
            type: CommunityType::LocationCommunity,
            data: ['name' => 'National Level Community for South Africa'],
            locatableType: LocatableType::Country,
            locatableId: 191,
        );

        // depth is only assigned in-memory when there IS a parent; parentless
        // rows take the column default, so read it back from the database.
        $this->assertNull($circle->parent_id);
        $this->assertSame(0, $circle->fresh()->depth);
    }

    /** No location circle for the place → parent stays unset, never guessed. */
    public function test_it_leaves_the_parent_unset_when_the_location_has_no_circle(): void
    {
        $circle = app(CircleCreationService::class)->create(
            type: CommunityType::Organisation,
            data: ['name' => 'National Body'],
        );

        $this->assertNull($circle->parent_id);
    }

    /** The root LocationCommunity circle for South Africa (Country #191). */
    private function insertCountryCircle(): int
    {
        $id = DB::table('circles')->insertGetId([
            'circleable_type' => CommunityType::LocationCommunity->value,
            'locatable_type' => LocatableType::Country->value,
            'locatable_id' => 191,
            'depth' => 0,
        ]);

        DB::table('circles')->where('id', $id)->update(['path' => (string) $id]);

        return $id;
    }
}
