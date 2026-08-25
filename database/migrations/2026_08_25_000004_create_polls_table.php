<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();

            // REQUIRED: every poll belongs to exactly one group; there are no
            // loose polls and no default "General" group. restrictOnDelete
            // because a concluded poll is a record of a community decision and
            // must not be destroyable by tidying up a shelf. See docs/adr/0003.
            $table->foreignId('poll_group_id')->constrained('poll_groups')->restrictOnDelete();

            // The Organiser. Nullable so the FK can SET NULL (always populated
            // at creation). Authority to Conclude/Cancel comes from being the
            // Organiser AND still a member, or from being a circle admin.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // PollEligibility — mirrors ForumGroupVisibility's vocabulary for
            // the same two rules. No 'public' case.
            $table->string('eligibility')->default('private');

            // The membership cut-off the Electorate is drawn from. Defaults to
            // the publish moment; may be set EARLIER so that joining after a
            // poll is announced confers no vote. NEVER later — which is why
            // the snapshot can always be taken at publish, with no job.
            $table->timestamp('qualifying_date')->nullable();

            $table->boolean('allow_response_update')->default(false);

            // Display rule ONLY — identity is always stored. Withheld from
            // EVERYONE (members, the Organiser, platform admins, superadmins),
            // the sole exception being a user viewing their own response.
            // This is NOT a secret ballot; never surface it as "anonymous".
            $table->boolean('hide_voter_identities')->default(true);

            // Once CLOSED, may the Result be seen from outside the circle? The
            // poll itself is never externally visible while it runs. Costs no
            // privacy: nothing in a Result is attributed.
            $table->boolean('publish_results')->default(false);

            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();

            // PollStatus: draft | published | concluded | cancelled. Records
            // WHY a poll stopped early, never WHETHER it is open — Scheduled,
            // Open and Closed are derived from the timestamps above.
            // Concluding and cancelling BOTH stamp closes_at, so status
            // annotates the clock rather than competing with it. adr/0001.
            $table->string('status')->default('draft');

            // Filed away; orthogonal to how the poll ended, so archiving never
            // overwrites the fact that it was Concluded or Cancelled.
            $table->timestamp('archived_at')->nullable();

            // The frozen Result: per-option totals, turnout, winning option.
            // Written ONCE at close. A column rather than a table because it
            // is small and always read whole, and nothing joins to it — the
            // opposite of poll_electorate's profile. Detail a recomputation
            // reproduces exactly (an IRV elimination sequence) is NOT stored.
            // A Cancelled poll never gets one.
            $table->json('result')->nullable();
            $table->timestamp('result_frozen_at')->nullable();

            // Reserved for a future "completion action" (e.g. grant a role on
            // an election result) — no defined shape yet, same pattern as
            // forum_groups.settings.
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('circle_id');
            $table->index('poll_group_id');
            $table->index('status');
            $table->index('created_by');
            $table->index(['circle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
