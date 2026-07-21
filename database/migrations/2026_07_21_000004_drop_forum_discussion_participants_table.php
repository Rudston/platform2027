<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire the explicit Join/Leave subscription: participant counts are now
     * derived from contributions (a discussion's creator ∪ its commenters), so
     * this table is no longer read anywhere. down() recreates it verbatim.
     */
    public function up(): void
    {
        Schema::dropIfExists('forum_discussion_participants');
    }

    public function down(): void
    {
        Schema::create('forum_discussion_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('forum_discussion_id')->constrained('forum_discussions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            $table->index(['forum_discussion_id', 'user_id', 'left_at'], 'fdp_discussion_user_left_idx');
        });
    }
};
