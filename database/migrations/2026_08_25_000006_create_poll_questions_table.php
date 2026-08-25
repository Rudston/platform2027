<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Structural grouping of a poll's options. "Question" NEVER surfaces in
        // the UI — a poll has a title, a description and a Prompt (the `text`
        // below). The table exists in this shape so a future Surveys service
        // can hold several per instance without restructuring; the Polls
        // creation UI only ever produces one, at position 0.
        Schema::create('poll_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();

            $table->integer('position')->default(0);

            // The Prompt: the instruction a Respondent reads above the
            // options, e.g. "Select ONE from:".
            $table->text('text');

            // PollResponseShape — what the Respondent does.
            $table->string('type');

            // TallyMethod — the arithmetic. Two axes, not one: constrain legal
            // pairings via PollResponseShape::allowedTallyMethods(), the only
            // place that rule lives.
            $table->string('tally_method');

            // ONLY meaningful when type = ranked_choice: a response must rank
            // every option, 1..N, no duplicates.
            $table->boolean('require_full_ranking')->default(false);

            // ONLY set when type = rating. restrictOnDelete: a curated scale
            // must not be deletable while a poll still references it, or that
            // poll's stored responses become unreadable.
            $table->foreignId('rating_scale_id')->nullable()
                ->constrained('poll_rating_scales')->restrictOnDelete();

            $table->timestamps();

            $table->index('poll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_questions');
    }
};
