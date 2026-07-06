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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            // Stable lookup handle used in code to send this template.
            $table->string('key', 150)->unique();
            // Admin-facing note describing when/where the template is used.
            $table->string('description', 255)->nullable();
            // Translatable per-locale values (Spatie HasTranslations), e.g.
            // {"en": "...", "pt_BR": "..."}.
            $table->json('subject');
            $table->json('body');
            $table->boolean('is_html')->default(true);
            // Whitelist of variable names available to this template, e.g.
            // ["user_name", "circle_name"]. Nullable when none are declared.
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
