<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Resolves the circular FK between district_municipalities and cities.
        // Added after `cities` exists. Nullable: not every district
        // municipality has a designated main city.
        Schema::table('district_municipalities', function (Blueprint $table) {
            $table->foreignId('main_city_id')->nullable()->after('province_id')->constrained('cities');
        });
    }

    public function down(): void
    {
        Schema::table('district_municipalities', function (Blueprint $table) {
            $table->dropForeign(['main_city_id']);
            $table->dropColumn('main_city_id');
        });
    }
};
