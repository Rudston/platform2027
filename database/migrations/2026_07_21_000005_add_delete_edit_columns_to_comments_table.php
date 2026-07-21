<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Manual (NOT SoftDeletes-trait) tombstone flag: a deleted comment
            // with replies is kept so its children still resolve a valid parent,
            // but rendered as "[deleted]". Hard delete (no replies) removes the
            // row outright and never touches these.
            $table->boolean('is_deleted')->default(false)->after('content');
            $table->timestamp('deleted_at')->nullable()->after('is_deleted');
            // Self vs admin-override is derived by comparing this to user_id
            // (no separate flag). Kept if that user is deleted.
            $table->foreignId('deleted_by_user_id')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();

            // Stamped whenever a save actually changes the content → "(Edited)".
            $table->timestamp('edited_at')->nullable()->after('deleted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_user_id');
            $table->dropColumn(['is_deleted', 'deleted_at', 'edited_at']);
        });
    }
};
