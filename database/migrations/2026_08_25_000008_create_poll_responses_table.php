<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_question_id')->constrained('poll_questions')->cascadeOnDelete();

            // Identity is ALWAYS stored, even when hide_voter_identities is
            // true — that flag governs display, not storage. This is why no
            // poll here is a secret ballot.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('submitted_at');

            $table->timestamps();

            // One response per Respondent per question. When the poll allows
            // response updates this row is updated in place (and submitted_at
            // refreshed) rather than a second row inserted — so the count can
            // never be stuffed.
            $table->unique(['poll_question_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_responses');
    }
};
