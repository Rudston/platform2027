<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            // The internal platform user accountable for actioning this request
            // (resolved via Circle::responsibleAdminFor). Distinct from the
            // requester and the external respondent; nullable when the platform
            // has no admin to route to. Any admin/superadmin may still act.
            $table->foreignId('responsible_admin_id')
                ->nullable()
                ->after('respondent_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_admin_id');
        });
    }
};
