<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250)->nullable();
            $table->string('code', 16)->nullable();
            $table->boolean('metropolis')->default(false);
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->foreignId('district_municipality_id')->nullable()->constrained('district_municipalities');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
