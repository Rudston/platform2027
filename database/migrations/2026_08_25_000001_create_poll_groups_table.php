<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_groups', function (Blueprint $table) {
            $table->id();

            // A group belongs to exactly one circle (its Polls tab).
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();

            // Preserve the group if the creating user is deleted — nullable so
            // the FK can SET NULL (always populated at creation).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->integer('position')->default(0);

            // ORGANISATIONAL ONLY — deliberately no visibility and no status.
            // A group never gates the polls inside it; access is answered by
            // the poll alone. Archived, never deleted: archiving leaves its
            // polls listed and findable. See docs/adr/0003.
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            // Slugs are unique per circle, NOT globally.
            $table->unique(['circle_id', 'slug']);
            $table->index('circle_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_groups');
    }
};
