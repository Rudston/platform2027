<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poll_rating_scales', function (Blueprint $table) {
            // RatingScalePresentation. How the respond form draws this scale.
            // A scale cannot be recognised by its name (admin-curated text) or
            // its shape (agreement and stars are both five points, 1..5), so it
            // declares the widget itself. Defaults to the dropdown, which is
            // correct for every existing scale.
            $table->string('presentation')->default('select')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('poll_rating_scales', function (Blueprint $table) {
            $table->dropColumn('presentation');
        });
    }
};
