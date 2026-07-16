<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_discussions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('forum_group_id')->constrained('forum_groups')->cascadeOnDelete();
            // Preserve discussion content if the creating user is deleted —
            // nullable so the FK can SET NULL (always populated at creation).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('content');
            $table->string('slug')->nullable();

            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);

            // Enum-backed strings (cast on the model).
            $table->string('status')->default('active');
            $table->string('moderation_status')->default('approved');
            $table->text('moderation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // FULLTEXT is MySQL-only; skip on other drivers (e.g. sqlite tests).
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['title', 'content']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_discussions');
    }
};
