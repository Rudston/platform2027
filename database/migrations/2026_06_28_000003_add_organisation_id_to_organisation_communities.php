<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisation_communities', function (Blueprint $table) {
            // Nullable: existing OrganisationCommunity records were created
            // without an Organisation entity.
            $table->foreignId('organisation_id')
                ->nullable()
                ->constrained('organisations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organisation_communities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
        });
    }
};
