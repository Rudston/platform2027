<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a response actually says, one row per option touched:
        //   single_choice — exactly ONE item, rank and rating point both null.
        //   ranked_choice — one item per option ranked (all of them when
        //                   require_full_ranking), each with a distinct rank.
        //   rating        — one item per option, each with a rating point.
        Schema::create('poll_response_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_response_id')->constrained('poll_responses')->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained('poll_options')->cascadeOnDelete();

            $table->integer('rank')->nullable();

            // restrictOnDelete for the same reason as poll_questions: a scale
            // point still referenced by a cast response must not vanish.
            $table->foreignId('rating_scale_point_id')->nullable()
                ->constrained('poll_rating_scale_points')->restrictOnDelete();

            $table->timestamps();

            // An option may be touched only once per response.
            $table->unique(['poll_response_id', 'poll_option_id']);

            // Ranks are distinct within a response. NULLs are not compared by
            // a unique index, so single_choice and rating responses (whose
            // ranks are all null) are unaffected — this constrains
            // ranked_choice only. Do NOT give `rank` a non-null default: that
            // would collapse every rating response into a collision.
            $table->unique(['poll_response_id', 'rank']);

            $table->index('poll_response_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_response_items');
    }
};
