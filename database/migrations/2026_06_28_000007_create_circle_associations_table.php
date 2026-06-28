<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_associations', function (Blueprint $table) {
            $table->foreignId('circle_id')
                ->constrained('circles')
                ->cascadeOnDelete();
            $table->foreignId('associated_circle_id')
                ->constrained('circles')
                ->cascadeOnDelete();

            // Extensible relationship kind, e.g. 'related', 'member_of', 'partner'.
            $table->string('association_type')->default('related');

            // Approval fields are stored now; the approval workflow lands later.
            $table->boolean('approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->primary(['circle_id', 'associated_circle_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_associations');
    }
};
