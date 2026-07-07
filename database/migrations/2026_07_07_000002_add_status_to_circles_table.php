<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            // Lifecycle state, backed by App\Enums\CircleStatus.
            $table->string('status')->default('active')->after('path');
        });

        // Ensure every existing circle is explicitly marked active.
        DB::table('circles')->update(['status' => 'active']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
