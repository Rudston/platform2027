<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comment_moderation_records', function (Blueprint $table) {
            // Snapshot of the owning forum group's visibility at flag time
            // (string, matching ForumGroupVisibility's backing values). Used to
            // hide Internal-group records from a plain platform admin.
            //
            // Forward-only + nullable: rows created before this column stay
            // NULL and are treated as UNRESTRICTED (never as 'internal') — NULL
            // means "flagged before this rule existed", not "internal". Only an
            // explicit 'internal' is ever restricted. Not a live reference: if a
            // group's visibility changes later, existing records keep whatever
            // was true when they were flagged.
            $table->string('forum_group_visibility')->nullable()->after('url_to_parent');
        });
    }

    public function down(): void
    {
        Schema::table('comment_moderation_records', function (Blueprint $table) {
            $table->dropColumn('forum_group_visibility');
        });
    }
};
