<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('article')
                ->constrained('countries')
                ->nullOnDelete();
        });

        // For now every province is South Africa (countries.id = 191).
        DB::table('provinces')->update(['country_id' => 191]);
    }

    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
