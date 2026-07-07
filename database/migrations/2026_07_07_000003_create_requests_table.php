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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();

            // Public-facing identifier used in URLs (never the auto-increment id).
            $table->char('ulid', 26)->unique();

            // e.g. organisation_approval, circle_join, location_request,
            // circle_association.
            $table->string('type');

            // pending, approved, denied, expired.
            $table->string('status')->default('pending');

            // 'external' (respondent is an email contact) or 'internal'
            // (respondent is a platform user).
            $table->string('direction');

            // Who raised the request.
            $table->foreignId('requester_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // The circle the request concerns (optional).
            $table->foreignId('circle_id')
                ->nullable()
                ->constrained('circles')
                ->nullOnDelete();

            // Polymorphic subject of the request (e.g. an Organisation).
            $table->nullableMorphs('requestable');

            // External respondent contact (when direction = external).
            $table->string('respondent_email')->nullable();

            // Internal respondent user (when direction = internal).
            $table->foreignId('respondent_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Single-use token embedded in approval/deny URLs.
            $table->string('token')->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();

            // When the respondent acted, and any note they left.
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();

            // Arbitrary contextual data for this request.
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
