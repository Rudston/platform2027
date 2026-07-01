<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coordinate_data', function (Blueprint $table) {
            // Supports the bounding-box pre-filter in CoordinateData::nearest().
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('coordinate_data', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
        });
    }
};
