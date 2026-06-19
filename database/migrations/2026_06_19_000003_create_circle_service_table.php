<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_service', function (Blueprint $table) {
            $table->foreignId('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->json('config')->nullable();          // per-circle service configuration
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // A service is attached to a circle at most once.
            $table->unique(['circle_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_service');
    }
};
