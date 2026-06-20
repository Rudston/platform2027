<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow GLOBAL role/permission assignments (no team context) by making the
 * team key `circle_id` nullable on the assignment pivots.
 *
 * A MySQL PRIMARY KEY cannot contain a nullable column, so the original
 * composite primary keys are dropped and rebuilt as UNIQUE indexes over the
 * same columns — which preserve the uniqueness guarantee while permitting the
 * nullable team key. Uses Laravel 12 native column modification (no dbal).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- model_has_roles: PK (circle_id, role_id, model_id, model_type) ----
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary();
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable()->change();
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unique(
                ['circle_id', 'role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_unique'
            );
        });

        // ---- model_has_permissions: PK (circle_id, permission_id, model_id, model_type) ----
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary();
        });
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable()->change();
        });
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unique(
                ['circle_id', 'permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_unique'
            );
        });
    }

    public function down(): void
    {
        // Reverse model_has_roles: unique -> NOT NULL -> primary key.
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropUnique('model_has_roles_role_model_type_unique');
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable(false)->change();
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->primary(
                ['circle_id', 'role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary'
            );
        });

        // Reverse model_has_permissions.
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropUnique('model_has_permissions_permission_model_type_unique');
        });
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable(false)->change();
        });
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->primary(
                ['circle_id', 'permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );
        });
    }
};
