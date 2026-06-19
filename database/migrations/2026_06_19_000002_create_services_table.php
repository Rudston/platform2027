<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');                   // e.g. "Manage Events"
            $table->string('key')->unique();          // e.g. "manage_events"
            $table->string('description')->nullable();
            $table->string('handler_class');          // e.g. App\Services\Circles\ManageEventsService
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
