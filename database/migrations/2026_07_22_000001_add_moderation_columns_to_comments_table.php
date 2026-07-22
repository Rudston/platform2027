<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Null = not yet run through the moderation checker (or invalidated
            // by a later edit — an edit nulls this to force a recheck). Non-null
            // = checked, whether or not it was flagged.
            $table->timestamp('ai_checked_at')->nullable()->after('flagged_as_offensive');

            // Admin "Hide" audit — mirrors the existing deleted_at /
            // deleted_by_user_id shape from the delete step. (`hidden` itself
            // already exists.)
            $table->timestamp('hidden_at')->nullable()->after('hidden');
            $table->foreignId('hidden_by_user_id')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by_user_id');
            $table->dropColumn(['ai_checked_at', 'hidden_at']);
        });
    }
};
