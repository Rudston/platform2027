<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PLATFORM vocabulary, deliberately WITHOUT a circle_id: scales are
        // curated centrally and shared by every circle, so "Strongly Agree"
        // means the same thing in two circles' results. Circle admins PICK a
        // scale, never mint one — the same treatment as themes.
        Schema::create('poll_rating_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_rating_scales');
    }
};
