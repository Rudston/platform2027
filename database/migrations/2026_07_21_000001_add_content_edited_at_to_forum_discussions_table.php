<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_discussions', function (Blueprint $table) {
            // Set when the author edits the first post's content. Null = never
            // edited (drives the "(Edited)" marker). Explicitly scoped to
            // content edits — future pin/lock/moderation changes must NOT set it.
            $table->timestamp('content_edited_at')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('forum_discussions', function (Blueprint $table) {
            $table->dropColumn('content_edited_at');
        });
    }
};
