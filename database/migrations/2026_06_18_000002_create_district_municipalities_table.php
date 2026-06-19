<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: main_city_id is intentionally omitted here to break the
        // circular FK with `cities`. It is added in a later migration
        // (add_main_city_to_district_municipalities) once `cities` exists.
        Schema::create('district_municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250)->nullable();
            $table->string('code', 10)->nullable();
            $table->string('seat', 250)->nullable();
            $table->string('population', 10)->nullable(); // varchar in source
            $table->string('province_str', 250)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_municipalities');
    }
};
