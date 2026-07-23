<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Who last edited the content (author OR a moderator via Edit &
            // Approve). last_edited_by_user_id !== user_id is the signal that an
            // admin touched the author's words — same audit shape as
            // deleted_by_user_id.
            $table->foreignId('last_edited_by_user_id')->nullable()->after('edited_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by_user_id');
        });
    }
};
