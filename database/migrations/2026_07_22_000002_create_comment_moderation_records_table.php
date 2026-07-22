<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_moderation_records', function (Blueprint $table) {
            $table->id();

            // Direct FK — Comment already carries its own commentable polymorphism,
            // so the legacy referenced_type/id + thread/message split is not ported.
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();

            // ModerationFlagSource: ai | user.
            $table->string('flagged_by');

            // Snapshot of the comment content at the moment this record was
            // created — NOT a live reference (this is what makes fixed_by_author
            // meaningful). Author derived from comment.user_id, never denormalised.
            $table->text('content');

            // The checker's reasoning when flagged_by = ai; null for user flags.
            $table->text('ai_message')->nullable();

            $table->boolean('moderated')->default(false);
            $table->boolean('moderated_as_ok')->default(false);

            // ModerationAction: approved | hidden | deleted — null until an admin acts.
            $table->string('moderation_action')->nullable();
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Set when the author edits the comment AFTER this record but BEFORE
            // an admin acts — captures the new content for before/after comparison.
            $table->boolean('fixed_by_author')->default(false);
            $table->text('moderated_content')->nullable();

            $table->timestamps();

            // Backs the "is there already a pending record for this comment+source?"
            // dedupe lookup. Explicit short name (MySQL 64-char identifier limit).
            $table->index(['comment_id', 'flagged_by', 'moderated'], 'cmr_comment_source_moderated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_moderation_records');
    }
};
