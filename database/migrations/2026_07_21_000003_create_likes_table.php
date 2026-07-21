<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();

            // Polymorphic target — a Comment today, reusable for a Discussion,
            // Circle, etc. later.
            $table->morphs('likeable');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            // A user may like a given thing at most once.
            $table->unique(['likeable_type', 'likeable_id', 'user_id'], 'likes_likeable_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
