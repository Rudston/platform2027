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
        Schema::create('local_municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250)->nullable();
            $table->string('code', 10)->nullable();
            $table->string('seat', 250)->nullable();
            $table->integer('population')->default(0); // int in source
            $table->foreignId('district_municipality_id')->nullable()->constrained('district_municipalities');
            $table->string('district', 250)->nullable();
            $table->string('population_str', 250)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_municipalities');
    }
};
