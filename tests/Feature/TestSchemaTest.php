<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSchema;
use Tests\TestCase;

/**
 * The shared schema builder every table-hungry test opts into.
 *
 * CLAUDE.md forbids RefreshDatabase because the full migration set cannot run
 * on sqlite (a demography backfill references a `countries` table no migration
 * creates), so a test builds only the tables it asks for. These assertions pin
 * that: opting into one thing must never quietly build everything.
 */
class TestSchemaTest extends TestCase
{
    public function test_circles_builds_the_circles_table_and_nothing_else(): void
    {
        TestSchema::make()->circles();

        $this->assertTrue(Schema::hasTable('circles'));
        $this->assertFalse(Schema::hasTable('circle_memberships'));
        $this->assertFalse(Schema::hasTable('polls'));
        $this->assertFalse(Schema::hasTable('taggables'));
    }

    public function test_the_circles_table_carries_the_columns_the_model_reads(): void
    {
        TestSchema::make()->circles();

        foreach ([
            'circleable_type', 'circleable_id', 'locatable_type', 'locatable_id',
            'parent_id', 'path', 'depth', 'name', 'description', 'status',
            'is_test', 'deleted_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('circles', $column), "circles.$column is missing");
        }
    }

    public function test_each_concern_can_be_opted_into_on_its_own(): void
    {
        TestSchema::make()->users()->permissions()->memberships()->tagging();

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('model_has_roles'));
        $this->assertTrue(Schema::hasTable('circle_memberships'));
        $this->assertTrue(Schema::hasTable('themes'));
        $this->assertTrue(Schema::hasTable('taggables'));

        $this->assertFalse(Schema::hasTable('polls'), 'nothing asked for the poll tables');
    }

    public function test_memberships_pulls_in_the_tables_its_foreign_keys_need(): void
    {
        TestSchema::make()->memberships();

        $this->assertTrue(Schema::hasTable('circles'));
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_polls_builds_every_poll_table(): void
    {
        TestSchema::make()->polls();

        foreach ([
            'poll_groups', 'polls', 'poll_electorate', 'poll_questions', 'poll_options',
            'poll_responses', 'poll_response_items', 'poll_rating_scales',
            'poll_rating_scale_points',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "$table is missing");
        }
    }

    /**
     * Matched by NAME, not by a hard-coded date list: a poll migration added
     * later must be picked up here too, or every poll test silently runs
     * against a stale schema.
     */
    public function test_poll_migrations_are_matched_by_name_so_a_later_one_is_picked_up(): void
    {
        $schema = TestSchema::make()->polls();

        $this->assertSame(
            glob(database_path('migrations/*_poll*.php')),
            array_values(array_intersect(
                $schema->appliedMigrations(),
                glob(database_path('migrations/*_poll*.php')),
            )),
            'every migration whose name mentions polls must have been applied',
        );

        // Proof that the ALTERs following the creates run too: this column
        // arrives in a migration dated after every create.
        $this->assertTrue(Schema::hasColumn('poll_rating_scales', 'presentation'));
    }

    public function test_the_rating_scales_can_be_asked_for_without_the_rest_of_polls(): void
    {
        TestSchema::make()->pollRatingScales();

        $this->assertTrue(Schema::hasTable('poll_rating_scales'));
        $this->assertTrue(Schema::hasTable('poll_rating_scale_points'));
        $this->assertTrue(Schema::hasColumn('poll_rating_scales', 'presentation'));

        $this->assertFalse(Schema::hasTable('polls'), 'the seeder needs the scales alone');
    }

    /**
     * A test still hand-rolling its own `circles` must be able to adopt an
     * opt-in that needs one — that is the migration path for the ~18 older
     * tests, so the hand-built tables check the schema, not just this builder.
     */
    public function test_a_table_the_test_already_built_itself_is_left_alone(): void
    {
        Schema::create('circles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        TestSchema::make()->memberships();

        $this->assertTrue(Schema::hasTable('circle_memberships'));
        $this->assertFalse(
            Schema::hasColumn('circles', 'status'),
            "the test's own circles table is kept as-is, not replaced",
        );
    }

    /**
     * Overlapping and repeated opt-ins are the normal case — polls() already
     * covers the rating scales — so a second ask must be a no-op rather than a
     * "table already exists".
     */
    public function test_opting_in_twice_is_a_no_op(): void
    {
        $schema = TestSchema::make()->circles()->polls()->pollRatingScales()->circles()->polls();

        $this->assertTrue(Schema::hasTable('polls'));
        $this->assertSame(
            count($schema->appliedMigrations()),
            count(array_unique($schema->appliedMigrations())),
            'no migration ran twice',
        );
    }
}
