<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinkMainPlaces extends Command
{
    protected $signature = 'demography:link-main-places';

    protected $description = 'Populate main_places.local_municipality_id and city_id by matching code to local_municipalities/cities (one-off).';

    public function handle(): int
    {
        // Codes are unique within each table, so a JOIN on code yields at most
        // one match — safe to set the id directly. Idempotent: re-running just
        // re-applies the same matches.
        $lmLinked = DB::table('main_places as mp')
            ->join('local_municipalities as lm', 'mp.code', '=', 'lm.code')
            ->update(['mp.local_municipality_id' => DB::raw('lm.id')]);

        $cityLinked = DB::table('main_places as mp')
            ->join('cities as c', 'mp.code', '=', 'c.code')
            ->update(['mp.city_id' => DB::raw('c.id')]);

        $this->info("Set local_municipality_id on {$lmLinked} main_places.");
        $this->info("Set city_id on {$cityLinked} main_places.");

        $total    = DB::table('main_places')->count();
        $unlinked = DB::table('main_places')
            ->whereNull('local_municipality_id')
            ->whereNull('city_id')
            ->count();

        $this->info("Coverage: {$total} total, {$unlinked} unlinked (no matching code).");

        return self::SUCCESS;
    }
}
