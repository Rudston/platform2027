<?php

namespace Tests\Feature;

use App\Enums\CommunityType;
use App\Mail\TemplateMailable;
use App\Models\Circles\Circle;
use App\Models\Communication\Request;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Communication\EmailServiceHandler;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers request -> responsible-admin routing:
 *   - Circle::responsibleAdminFor() resolution (LocationCommunity-only climb,
 *     then global admin, then superadmin, then null),
 *   - Request::createForOrganisation() storing responsible_admin_id, and
 *   - the admin-notice email template rendering via EmailServiceHandler.
 *
 * The full migration set cannot run on the sqlite test database (a demography
 * backfill references a `countries` table no migration creates), so we build
 * only the tables these tests touch. The `circles` table is hand-built to avoid
 * the JSON ->change() migration, which is MySQL-specific.
 */
class ResponsibleAdminRoutingTest extends TestCase
{
    private const USER_MORPH = User::class;

    protected function setUp(): void
    {
        parent::setUp();

        (include database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include database_path('migrations/2026_06_20_132319_create_permission_tables.php'))->up();
        (include database_path('migrations/2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php'))->up();
        (include database_path('migrations/2026_07_07_000003_create_requests_table.php'))->up();
        (include database_path('migrations/2026_07_14_000001_add_responsible_admin_id_to_requests_table.php'))->up();
        (include database_path('migrations/2026_07_06_000001_create_email_templates_table.php'))->up();

        $this->buildCirclesTable();
    }

    /** Minimal circles schema: only the columns the routing logic touches. */
    private function buildCirclesTable(): void
    {
        Schema::create('circles', function ($table): void {
            $table->id();
            $table->string('circleable_type')->nullable();
            $table->unsignedBigInteger('circleable_id')->nullable();
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

    /**
     * Insert a circle and set its materialised path to <parentPath>/<id>.
     *
     * @return int  the new circle id
     */
    private function makeCircle(CommunityType $type, ?int $parentId = null, ?string $parentPath = null, int $depth = 0): int
    {
        $id = DB::table('circles')->insertGetId([
            'circleable_type' => $type->value,
            'parent_id' => $parentId,
            'depth' => $depth,
        ]);

        $path = $parentPath ? "{$parentPath}/{$id}" : (string) $id;
        DB::table('circles')->where('id', $id)->update(['path' => $path]);

        return $id;
    }

    private function makeRole(string $name): int
    {
        return DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'circle_id' => null,
        ]);
    }

    /** Grant $user the given role, optionally scoped to a circle (team). */
    private function grantRole(int $userId, int $roleId, ?int $circleId = null): void
    {
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => self::USER_MORPH,
            'model_id' => $userId,
            'circle_id' => $circleId,
        ]);
    }

    /**
     * Build a Location > Location > Organisation chain and return the ids.
     *
     * @return array{root:int, child:int, org:int}
     */
    private function makeChain(): array
    {
        $root = $this->makeCircle(CommunityType::LocationCommunity, depth: 0);
        $rootPath = (string) $root;

        $child = $this->makeCircle(CommunityType::LocationCommunity, parentId: $root, parentPath: $rootPath, depth: 1);
        $childPath = "{$root}/{$child}";

        $org = $this->makeCircle(CommunityType::Organisation, parentId: $child, parentPath: $childPath, depth: 2);

        return ['root' => $root, 'child' => $child, 'org' => $org];
    }

    public function test_it_returns_the_nearest_location_community_admin(): void
    {
        $chain = $this->makeChain();
        $circleAdminRole = $this->makeRole('circle_admin');

        // Admins on BOTH location levels — the nearest (child) must win.
        $rootAdmin = User::factory()->create();
        $childAdmin = User::factory()->create();
        $this->grantRole($rootAdmin->id, $circleAdminRole, $chain['root']);
        $this->grantRole($childAdmin->id, $circleAdminRole, $chain['child']);

        $org = Circle::find($chain['org']);
        $this->assertSame($childAdmin->id, Circle::responsibleAdminFor($org)?->id);
    }

    public function test_it_skips_admins_on_non_location_circles(): void
    {
        // A circle_admin exists ONLY on the organisation circle itself. The
        // climb is LocationCommunity-only, so it must be ignored and — with no
        // global admin/superadmin — the result is null.
        $chain = $this->makeChain();
        $circleAdminRole = $this->makeRole('circle_admin');

        $orgAdmin = User::factory()->create();
        $this->grantRole($orgAdmin->id, $circleAdminRole, $chain['org']);

        $org = Circle::find($chain['org']);
        $this->assertNull(Circle::responsibleAdminFor($org));
    }

    public function test_it_falls_back_to_global_admin_then_superadmin(): void
    {
        $chain = $this->makeChain();
        $org = Circle::find($chain['org']);

        // No circle_admins anywhere → falls back to the first global admin.
        $adminRole = $this->makeRole('admin');
        $superRole = $this->makeRole('superadmin');

        $superadmin = User::factory()->create();
        $this->grantRole($superadmin->id, $superRole);
        // admin wins over superadmin when both exist.
        $admin = User::factory()->create();
        $this->grantRole($admin->id, $adminRole);

        $this->assertSame($admin->id, Circle::responsibleAdminFor($org)?->id);
    }

    public function test_it_returns_null_when_no_admin_exists(): void
    {
        $chain = $this->makeChain();
        $org = Circle::find($chain['org']);

        $this->assertNull(Circle::responsibleAdminFor($org));
    }

    public function test_create_for_organisation_stores_the_responsible_admin(): void
    {
        $chain = $this->makeChain();
        $circleAdminRole = $this->makeRole('circle_admin');

        $steward = User::factory()->create();
        $this->grantRole($steward->id, $circleAdminRole, $chain['child']);

        $requester = User::factory()->create();
        $orgCircle = Circle::find($chain['org']);
        $organisation = (new Organisation)->forceFill(['id' => 1]);

        $request = Request::createForOrganisation(
            requester: $requester,
            circle: $orgCircle,
            organisation: $organisation,
            respondentEmail: 'contact@example.org',
        );

        $this->assertSame($steward->id, $request->responsible_admin_id);
        $this->assertSame($steward->id, $request->responsibleAdmin?->id);
    }

    public function test_the_admin_notice_template_renders_and_sends(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $admin = User::factory()->create(['name' => 'Thandi', 'email' => 'thandi@mobilize.org.za']);

        (new EmailServiceHandler)->sendTemplate(
            'email.organisation_approval_admin_notice',
            $admin->email,
            [
                'admin_name' => $admin->name,
                'organisation_name' => 'Cape Town Cyclists',
                'requester_name' => 'Sipho',
                'review_url' => 'https://example.test/admin/requests/1',
            ],
        );

        Mail::assertSent(TemplateMailable::class, function (TemplateMailable $mail) use ($admin): bool {
            return $mail->hasTo($admin->email)
                && str_contains($mail->render(), 'Cape Town Cyclists')
                && ! str_contains($mail->render(), '{{');
        });
    }
}
