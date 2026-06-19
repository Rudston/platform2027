<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Source column `LocationID` is intentionally dropped: the `Location`
        // table is excluded from this migration entirely.
        Schema::create('urban_places', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250)->nullable();
            $table->string('code', 10)->nullable();
            $table->string('population', 250)->nullable(); // varchar in source
            $table->string('language', 250)->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities');
            $table->string('city_str', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_places');
    }
};
