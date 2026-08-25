<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Electorate: SNAPSHOTTED at publish, not derived. Written once,
        // in one pass over the append-only circle_memberships log as of the
        // poll's qualifying_date, and authoritative from then on.
        //
        // circle_memberships is append-only, so membership on a past date LOOKS
        // derivable and this table LOOKS like redundant denormalisation. It
        // isn't: metadata.internal_role_approved is mutated in place and keeps
        // no history, so deriving answers with TODAY's approvals for a past
        // date — a wrong electorate on exactly the polls whose eligibility is
        // most restrictive. Snapshotting also keeps the turnout denominator
        // stable while a poll is open. See docs/adr/0002 before removing this.
        Schema::create('poll_electorate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // One entitlement per user per poll.
            $table->unique(['poll_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_electorate');
    }
};
