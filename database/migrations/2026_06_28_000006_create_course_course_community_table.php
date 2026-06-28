<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_course_community', function (Blueprint $table) {
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();
            $table->foreignId('course_community_id')
                ->constrained('course_communities')
                ->cascadeOnDelete();
            $table->primary(['course_id', 'course_community_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_course_community');
    }
};
