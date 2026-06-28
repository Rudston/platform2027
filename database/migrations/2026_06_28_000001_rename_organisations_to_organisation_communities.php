<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('organisations', 'organisation_communities');
    }

    public function down(): void
    {
        Schema::rename('organisation_communities', 'organisations');
    }
};
