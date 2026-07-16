<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Community-specific role WITHIN the circle (e.g. 'organisation_member').
            // Distinct from Spatie circle roles; null when the type has no such concept.
            $table->string('internal_role')->nullable();

            $table->timestamp('joined_at');
            // NULL = currently active. Rows are never deleted, only closed here.
            $table->timestamp('left_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['circle_id', 'user_id']);
            $table->index('left_at');   // fast "active memberships" lookups
            $table->index('user_id');   // "this user's memberships across all circles" (limit check)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_memberships');
    }
};
