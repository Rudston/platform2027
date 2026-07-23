<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();

            // Upserted on each visit → the ordering key for "recently visited".
            $table->timestamp('last_visited_at');
            $table->timestamps();

            // One row per user+circle (visits dedupe to distinct communities).
            $table->unique(['user_id', 'circle_id'], 'circle_visits_user_circle_unique');
            // Backs "this user's visits, most recent first".
            $table->index(['user_id', 'last_visited_at'], 'circle_visits_user_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_visits');
    }
};
