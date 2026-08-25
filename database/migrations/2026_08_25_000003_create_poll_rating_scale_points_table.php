<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_rating_scale_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_rating_scale_id')->constrained('poll_rating_scales')->cascadeOnDelete();

            $table->string('label');            // e.g. "Strongly Agree"
            $table->integer('value');           // numeric value used in tallying
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('poll_rating_scale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_rating_scale_points');
    }
};
