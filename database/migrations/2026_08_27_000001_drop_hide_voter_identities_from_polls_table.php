<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribution is unconditional, so there is nothing to store.
 *
 * `hide_voter_identities` could only ever hold `true`: withholding who chose
 * what is a guarantee (US35, CONTEXT.md "Attribution"), not a per-poll setting.
 * A column with one legal value misrepresents what is configurable and leaves
 * the rule flippable directly in the database. See docs/adr/0004.
 *
 * NOT a soft removal: the compose control, the service field and the label are
 * gone too. If Attribution is ever wanted as a per-Poll choice it gets its own
 * decision, not a switch that quietly already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polls', function (Blueprint $table): void {
            $table->dropColumn('hide_voter_identities');
        });
    }

    /**
     * Restores the column, not the data — every row held the same value, which
     * is the whole reason it went.
     */
    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table): void {
            $table->boolean('hide_voter_identities')->default(true);
        });
    }
};
