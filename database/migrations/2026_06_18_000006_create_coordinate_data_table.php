<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone coordinate lookup. `city` is a plain string in the source
        // (not a foreign key). `province_id` is a loose legacy reference with
        // no index in the source, so it is indexed but NOT constrained here.
        Schema::create('coordinate_data', function (Blueprint $table) {
            $table->id();
            $table->string('city', 250)->nullable();
            $table->string('accent_city', 250)->nullable();
            $table->string('province_name', 120)->nullable();
            $table->float('latitude')->default(0);
            $table->float('longitude')->default(0);
            $table->unsignedBigInteger('province_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinate_data');
    }
};
