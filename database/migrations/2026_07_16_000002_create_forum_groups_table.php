<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_groups', function (Blueprint $table) {
            $table->id();

            // Every group belongs to exactly one circle (its Forums tab).
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();

            // Preserve group content if the creating user is deleted — nullable
            // so the FK can SET NULL (always populated at creation).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();

            // Enum-backed strings (cast on the model, per CircleStatus convention).
            $table->string('visibility')->default('public');
            $table->json('settings')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Slugs are unique per circle, NOT globally.
            $table->unique(['circle_id', 'slug']);
            $table->index('circle_id');
            $table->index('visibility');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_groups');
    }
};
