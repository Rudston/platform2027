<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            // Polymorphic parent (matches taggable_type/id, requestable_type/id).
            $table->morphs('commentable');

            // Self-nesting: null = root comment, non-null = reply at any depth.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->text('content');

            // Pinning is only meaningful for root comments — the "no pinning
            // replies" rule is enforced in the Comment model, not by the DB.
            $table->boolean('pinned')->default(false);
            $table->integer('pinned_position')->nullable();

            // Moderation columns exist now; the workflow is deferred.
            $table->boolean('hidden')->default(false);
            $table->boolean('flagged_as_offensive')->default(false);
            $table->boolean('moderated')->default(false);
            $table->text('message_to_moderator')->nullable();
            // The moderator (admin / superadmin / later a circle_admin). Kept if
            // that user is deleted.
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
