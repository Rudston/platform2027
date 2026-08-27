<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builds ONLY the tables a test asks for.
 *
 * CLAUDE.md forbids RefreshDatabase: the full migration set cannot run on
 * sqlite (a demography backfill references a `countries` table no migration
 * creates), so a test hand-rolls its own schema in setUp — the same 40-odd
 * lines copied across two dozen files. This is that block, extracted. The poll
 * tests use it; the older tests still carry their own copies, so retiring
 * those is the remaining work, not a claim this file already makes true.
 *
 * Every method is an independent opt-in, applies immediately, returns $this,
 * and is a no-op if this builder already did it — so overlapping asks never
 * collide (polls() already covers the rating scales). Where a table's foreign
 * keys need another table, that one is pulled in for you.
 *
 *     TestSchema::make()->users()->permissions()->circles()->polls();
 *
 * Use ONE builder per test: the bookkeeping is per-instance, so a second
 * make() re-runs the migrations of the first and sqlite rejects the duplicate
 * CREATE. The tables built here BY HAND are the exception — they check the
 * schema itself, so a test still hand-rolling its own `circles` can adopt an
 * opt-in that needs it without tripping over the table it already made.
 *
 * Tables NOT asked for are deliberately absent — a test that touches one it
 * did not declare should fail loudly rather than lean on a shared blob.
 */
final class TestSchema
{
    /**
     * Migration files already applied, absolute paths, in the order they ran.
     *
     * @var list<string>
     */
    private array $applied = [];

    /**
     * Tables built here by hand rather than by a migration, keyed by name.
     * Separate from $applied because the two hold different things: migration
     * file paths there, table names here. Both answer "already built".
     *
     * @var array<string, true>
     */
    private array $handBuilt = [];

    public static function make(): self
    {
        return new self;
    }

    public function users(): self
    {
        return $this->migrate('0001_01_01_000000_create_users_table.php');
    }

    /**
     * Spatie permission tables in teams mode, with the nullable circle_id that
     * lets a role be global as well as circle-scoped.
     */
    public function permissions(): self
    {
        return $this->migrate(
            '2026_06_20_132319_create_permission_tables.php',
            '2026_06_20_140000_make_circle_id_nullable_on_permission_pivots.php',
        );
    }

    /**
     * Hand-rolled rather than migrated: the real chain declares `circleable`
     * and `locatable` morphs NOT NULL and `name` required, while tests insert
     * bare circles through the query builder (deliberately, so
     * Circle::booted() does not fire and demand tables they never built).
     *
     * The columns are the SUPERSET of what the existing tests declare between
     * them (hence `is_test`, which the poll tests omitted), so a test still
     * carrying its own block can swap to this one without losing a column.
     */
    public function circles(): self
    {
        return $this->once('circles', function (): void {
            Schema::create('circles', function (Blueprint $table): void {
                $table->id();
                $table->string('circleable_type')->nullable();
                $table->unsignedBigInteger('circleable_id')->nullable();
                $table->string('locatable_type')->nullable();
                $table->unsignedBigInteger('locatable_id')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('path')->nullable();
                $table->integer('depth')->default(0);
                $table->string('name')->nullable();
                $table->json('description')->nullable();
                $table->string('status')->default('active');
                $table->boolean('is_test')->default(false);
                $table->softDeletes();
                $table->timestamps();
            });
        });
    }

    /**
     * Named explicitly rather than globbed: there is one membership migration
     * and no ALTERs after it, and a pattern loose enough to catch a future one
     * would also catch tables nobody asked for — which is the whole invariant
     * here. polls() globs because that family has ALTERs and keeps growing.
     */
    public function memberships(): self
    {
        return $this->circles()
            ->users()
            ->migrate('2026_07_16_000001_create_circle_memberships_table.php');
    }

    /**
     * The theme vocabulary plus the polymorphic pivot. `themes` is hand-rolled
     * because the model reads `name` where the original migration wrote
     * `label`.
     */
    public function tagging(): self
    {
        return $this->once('themes', function (): void {
            Schema::create('themes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->timestamps();
            });
        })->migrate('2026_07_17_000001_create_taggables_table.php');
    }

    /**
     * Every poll table, matched by NAME rather than by a hard-coded date list,
     * so a poll migration added later is picked up here too instead of leaving
     * the tests running against a stale schema.
     */
    public function polls(): self
    {
        return $this->circles()->users()->migrateMatching('*_poll*.php');
    }

    /**
     * The platform-curated rating scales on their own — they have no circle_id
     * and no other poll table depends on them, so the seeder's test needs
     * nothing else.
     */
    public function pollRatingScales(): self
    {
        return $this->migrateMatching('*_poll_rating_scale*.php');
    }

    /**
     * Absolute paths of the migrations this builder ran, in order.
     *
     * @return list<string>
     */
    public function appliedMigrations(): array
    {
        return $this->applied;
    }

    private function migrate(string ...$fileNames): self
    {
        return $this->apply(array_map(
            fn (string $name): string => database_path('migrations/'.$name),
            $fileNames,
        ));
    }

    /**
     * glob() returns paths sorted, which for date-prefixed migration files is
     * their run order — so a create always precedes the ALTERs that follow it.
     */
    private function migrateMatching(string $pattern): self
    {
        return $this->apply(glob(database_path('migrations/'.$pattern)) ?: []);
    }

    /**
     * @param  list<string>  $paths
     */
    private function apply(array $paths): self
    {
        foreach ($paths as $path) {
            if (in_array($path, $this->applied, true)) {
                continue;
            }

            $this->applied[] = $path;
            (include $path)->up();
        }

        return $this;
    }

    private function once(string $table, callable $build): self
    {
        if (isset($this->handBuilt[$table]) || Schema::hasTable($table)) {
            $this->handBuilt[$table] = true;

            return $this;
        }

        $this->handBuilt[$table] = true;
        $build();

        return $this;
    }
}
