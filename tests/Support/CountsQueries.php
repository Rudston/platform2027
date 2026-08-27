<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Count the queries a piece of work issues, for pinning "one query, not one per
 * row" where the repo relies on it.
 *
 * Assert an EQUALITY between two sizes rather than an absolute number: a future
 * constant query lands in both counts, so only a genuinely per-row query fails
 * the test — which is the property worth guarding. An absolute figure needs
 * updating for changes that were never the point.
 */
trait CountsQueries
{
    /**
     * The SQL of every query $work issued, in order.
     *
     * @return list<string>
     */
    protected function queriesDuring(callable $work): array
    {
        $wasLogging = DB::logging();

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $work();

            return array_map(
                fn (array $entry): string => (string) $entry['query'],
                DB::getQueryLog(),
            );
        } finally {
            // finally, so a throwing $work does not leave logging on for the
            // rest of the run.
            if (! $wasLogging) {
                DB::disableQueryLog();
            }
        }
    }

    /**
     * How many of those queries touched $table — pins WHY a count is flat, not
     * merely that it is.
     *
     * @param  list<string>  $queries
     */
    protected function queriesTouching(array $queries, string $table): int
    {
        return count(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, $table),
        ));
    }
}
