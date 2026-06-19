<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: the community type this circle wraps
            // (Organisation, LocationCommunity, ThemeCommunity, Campaign, Course).
            $table->morphs('circleable');

            // Self-referencing parent for nesting circles within circles.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('circles')
                ->nullOnDelete();

            $table->unsignedTinyInteger('depth')->default(0);

            // Materialised path of ancestor ids, e.g. "1/4/12".
            $table->string('path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circles');
    }
};
