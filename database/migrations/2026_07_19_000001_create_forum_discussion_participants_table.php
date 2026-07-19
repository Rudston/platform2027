<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_discussion_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('forum_discussion_id')->constrained('forum_discussions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('joined_at');
            // NULL = currently active. Rows are never deleted, only closed here
            // (mirrors circle_memberships). No unique constraint — re-joins add
            // a fresh row.
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            // Fast "active participants of this discussion / is this user in?"
            // lookups. Explicit short name — the auto-generated one exceeds
            // MySQL's 64-char identifier limit.
            $table->index(['forum_discussion_id', 'user_id', 'left_at'], 'fdp_discussion_user_left_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_discussion_participants');
    }
};
