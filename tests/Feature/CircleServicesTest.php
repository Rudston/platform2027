<?php

namespace Tests\Feature;

use App\Contracts\Circles\HasDefaultServices;
use App\Enums\CommunityType;
use App\Livewire\Communities\CommunityPage;
use App\Livewire\Communities\Services\EventsServiceContainer;
use App\Livewire\Communities\Services\Forums\ForumServiceContainer;
use App\Livewire\Communities\Services\NewsServiceContainer;
use App\Models\Circles\Circle;
use App\Models\Communities\LocationCommunity;
use App\Models\Communities\OrganisationCommunity;
use App\Models\Communities\ThemeCommunity;
use App\Services\Circles\ForumService;
use App\Services\Circles\ManageUsersService;
use App\Services\Communication\EmailServiceHandler;
use Database\Seeders\Circles\ServicesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Service-as-Livewire-container system: the CircleServiceContract extension,
 * the HasDefaultServices-driven ordered attachment, the backfill command, and
 * the Community Page tabs (incl. Livewire 4 dynamic-component rendering).
 *
 * Tables are hand-built (the full migration set can't run on sqlite, and the
 * translatable-column migrations use MySQL-specific change()).
 */
class CircleServicesTest extends TestCase
{
    private const LC = 'App\Models\Communities\LocationCommunity';

    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();

        $this->buildCirclesTable();

        Schema::create('services', function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');
            $table->string('handler_class');
            $table->string('container_component')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circle_service', function ($table): void {
            $table->foreignId('circle_id');
            $table->foreignId('service_id');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['circle_id', 'service_id']);
        });

        Schema::create('location_communities', function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('organisation_communities', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // A community type that does NOT implement HasDefaultServices, for the
        // backfill's "leave non-implementers alone" case.
        Schema::create('theme_communities', function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $this->seed(ServicesSeeder::class);
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

    /** Create a Circle wrapping a fresh LocationCommunity (booted attaches services). */
    private function makeLocationCircle(): Circle
    {
        $lc = LocationCommunity::create(['name' => 'Test Location']);

        return Circle::create([
            'circleable_type' => self::LC,
            'circleable_id' => $lc->id,
            'name' => 'Test Location',
        ]);
    }

    public function test_container_component_contract(): void
    {
        $this->assertSame(ForumServiceContainer::class, (new ForumService)->containerComponent());
        // No-UI handlers default to null via HasNoContainerComponent.
        $this->assertNull((new ManageUsersService)->containerComponent());
        $this->assertNull((new EmailServiceHandler)->containerComponent());
    }

    public function test_location_community_declares_ordered_default_services(): void
    {
        $lc = new LocationCommunity;

        $this->assertInstanceOf(HasDefaultServices::class, $lc);
        $this->assertSame(['news', 'events', 'forums', 'media', 'voting'], $lc->defaultServices());
    }

    public function test_creating_a_location_circle_attaches_its_default_services(): void
    {
        $circle = $this->makeLocationCircle();

        $this->assertEqualsCanonicalizing(
            ['news', 'events', 'forums', 'media', 'voting'],
            $circle->services()->pluck('key')->all(),
        );
    }

    public function test_backfill_attaches_only_missing_services(): void
    {
        $circle = $this->makeLocationCircle();

        // Simulate a legacy circle missing two of its default services.
        $mediaId = $circle->services()->where('key', 'media')->value('services.id');
        $votingId = $circle->services()->where('key', 'voting')->value('services.id');
        $circle->services()->detach([$mediaId, $votingId]);
        $this->assertCount(3, $circle->services()->get());

        // A circleable that does NOT implement HasDefaultServices must be left
        // untouched (ThemeCommunity — both Location and Organisation communities
        // DO implement it, so they aren't valid "non-implementer" fixtures).
        $theme = ThemeCommunity::create(['name' => 'Theme']);
        $themeCircle = Circle::create([
            'circleable_type' => CommunityType::ThemeCommunity->value,
            'circleable_id' => $theme->id,
            'name' => 'Theme',
        ]);

        Artisan::call('circles:backfill-services');

        $this->assertStringContainsString('1 circles updated', Artisan::output());
        $this->assertEqualsCanonicalizing(
            ['news', 'events', 'forums', 'media', 'voting'],
            $circle->fresh()->services()->pluck('key')->all(),
        );
        $this->assertCount(0, $themeCircle->services()->get());
    }

    public function test_organisation_community_also_gets_default_services(): void
    {
        $this->assertInstanceOf(HasDefaultServices::class, new OrganisationCommunity);

        $org = OrganisationCommunity::create(['name' => 'Org']);
        $circle = Circle::create([
            'circleable_type' => CommunityType::Organisation->value,
            'circleable_id' => $org->id,
            'name' => 'Org',
        ]);

        $this->assertEqualsCanonicalizing(
            ['news', 'events', 'forums', 'media', 'voting'],
            $circle->services()->pluck('key')->all(),
        );
    }

    public function test_community_page_tab_ordering_and_switching(): void
    {
        $circle = $this->makeLocationCircle();

        // Exercise the tab logic on a plain component instance (Livewire::test
        // on a full-page #[Layout] component has snapshot/layout quirks).
        $page = new CommunityPage;
        $page->circle = $circle->load(['circleable', 'locatable', 'services']);

        // Tabs ordered per LocationCommunity::defaultServices().
        $this->assertSame(
            ['news', 'events', 'forums', 'media', 'voting'],
            $page->serviceTabs()->pluck('key')->all(),
        );

        // Active container is the FQCN the <livewire:dynamic-component> renders.
        $page->activeServiceKey = 'news';
        $this->assertSame(NewsServiceContainer::class, $page->activeContainer());

        $page->selectService('forums');
        $this->assertSame('forums', $page->activeServiceKey);
        $this->assertSame(ForumServiceContainer::class, $page->activeContainer());

        // A key with no container (manage_users) is not a tab and is ignored.
        $page->selectService('manage_users');
        $this->assertSame('forums', $page->activeServiceKey);
    }

    public function test_service_containers_mount_and_render(): void
    {
        $circle = $this->makeLocationCircle();

        // Still-placeholder containers (Forum now has its own real UI + test).
        Livewire::test(NewsServiceContainer::class, ['circle' => $circle])
            ->assertSee('NewsServiceContainer')
            ->assertSee('circle #'.$circle->id);

        Livewire::test(EventsServiceContainer::class, ['circle' => $circle])
            ->assertSee('EventsServiceContainer');
    }
}
