<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_question_id')->constrained('poll_questions')->cascadeOnDelete();

            // Candidate name or proposal text — an election and a proposition
            // differ only in what goes here, never structurally.
            $table->string('label');
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('poll_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_options');
    }
};
