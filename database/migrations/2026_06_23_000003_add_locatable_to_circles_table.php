<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every circle always has a location (minimum: country level), so the
        // polymorphic locatable columns are NOT nullable. Safe to add as the
        // circles table is empty.
        Schema::table('circles', function (Blueprint $table) {
            $table->morphs('locatable');
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropMorphs('locatable');
        });
    }
};
