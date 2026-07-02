<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wrap existing plain names as JSON {"en": "..."} (while still varchar).
        DB::statement("UPDATE services SET name = JSON_OBJECT('en', name) WHERE name IS NOT NULL AND name <> ''");

        Schema::table('services', function (Blueprint $table) {
            $table->json('name')->change();
        });
    }

    public function down(): void
    {
        // Back to varchar first (a plain string can't live in a json column)…
        Schema::table('services', function (Blueprint $table) {
            $table->string('name')->change();
        });

        // …then unwrap the English value.
        DB::statement("UPDATE services SET name = JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) WHERE name IS NOT NULL AND JSON_VALID(name)");
    }
};
