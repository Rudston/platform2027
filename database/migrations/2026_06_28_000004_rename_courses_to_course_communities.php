<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('courses', 'course_communities');
    }

    public function down(): void
    {
        Schema::rename('course_communities', 'courses');
    }
};
