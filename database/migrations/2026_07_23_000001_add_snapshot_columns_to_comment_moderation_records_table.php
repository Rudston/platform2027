<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comment_moderation_records', function (Blueprint $table) {
            // Snapshot audit fields, captured at record-creation time.
            // circle_id is nullable + nullOnDelete: the record must survive the
            // circle being deleted (it's an audit trail, not a live reference).
            $table->foreignId('circle_id')->nullable()->after('comment_id')->constrained('circles')->nullOnDelete();

            // Human label for the commentable kind (e.g. "Forum Discussion") —
            // never the raw class name.
            $table->string('commentable_type_label')->nullable()->after('circle_id');

            // Front-end URL to the commentable at creation time. A SNAPSHOT — may
            // go stale if a slug later changes; accepted for an audit log.
            $table->text('url_to_parent')->nullable()->after('commentable_type_label');
        });
    }

    public function down(): void
    {
        Schema::table('comment_moderation_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('circle_id');
            $table->dropColumn(['commentable_type_label', 'url_to_parent']);
        });
    }
};
