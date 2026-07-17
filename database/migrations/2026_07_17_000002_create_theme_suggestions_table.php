<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_suggestions', function (Blueprint $table) {
            $table->id();

            $table->string('name');            // proposed tag name
            $table->text('description')->nullable();
            $table->string('status')->default('pending');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // If the suggestion arose while tagging a specific entity, record it
            // so approval can auto-attach the new Theme to that entity.
            $table->string('origin_taggable_type')->nullable();
            $table->unsignedBigInteger('origin_taggable_id')->nullable();

            $table->timestamps();

            $table->index(['origin_taggable_type', 'origin_taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_suggestions');
    }
};
