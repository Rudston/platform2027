<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 150)->unique();
            $table->string('description', 255);
            // Translatable per-locale values (Spatie HasTranslations), e.g.
            // {"en": "...", "pt_BR": "..."}. Nullable so a block can exist
            // before any locale content is entered.
            $table->json('content')->nullable();
            $table->boolean('is_html')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
