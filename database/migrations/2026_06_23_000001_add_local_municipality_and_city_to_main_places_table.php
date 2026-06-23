<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('main_places', function (Blueprint $table) {
            // Linked by matching main_places.code to the respective table's code.
            // Nullable: a place links to a local municipality OR a city.
            $table->foreignId('local_municipality_id')
                ->nullable()
                ->after('population')
                ->constrained('local_municipalities')
                ->nullOnDelete();

            $table->foreignId('city_id')
                ->nullable()
                ->after('local_municipality_id')
                ->constrained('cities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('main_places', function (Blueprint $table) {
            $table->dropForeign(['local_municipality_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn(['local_municipality_id', 'city_id']);
        });
    }
};
