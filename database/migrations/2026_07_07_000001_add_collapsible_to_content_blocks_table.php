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
        Schema::table('content_blocks', function (Blueprint $table) {
            // Translatable per-locale heading shown when the block is
            // rendered as a collapsible disclosure. Nullable — only needed
            // for collapsible blocks. e.g. {"en": "...", "pt_BR": "..."}.
            $table->json('title')->nullable()->after('content');

            // When true, the block renders as an expand/collapse disclosure.
            $table->boolean('collapsible')->default(false)->after('is_html');

            // Initial state of a collapsible block (ignored when not collapsible).
            $table->boolean('default_collapsed')->default(true)->after('collapsible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_blocks', function (Blueprint $table) {
            $table->dropColumn(['title', 'collapsible', 'default_collapsed']);
        });
    }
};
