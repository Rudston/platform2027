<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wrap existing plain-text descriptions as JSON {"en": "..."} (while still text).
        DB::statement("UPDATE circles SET description = JSON_OBJECT('en', description) WHERE description IS NOT NULL AND description <> ''");

        Schema::table('circles', function (Blueprint $table) {
            $table->json('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        // Unwrap the English value back to plain text.
        DB::statement("UPDATE circles SET description = JSON_UNQUOTE(JSON_EXTRACT(description, '$.en')) WHERE description IS NOT NULL AND JSON_VALID(description)");
    }
};
