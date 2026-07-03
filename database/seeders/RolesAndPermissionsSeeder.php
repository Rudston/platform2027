<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear any cached roles/permissions before seeding.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create role definitions with NO team context (circle_id = null) so
        // they are reusable across every circle. The global-vs-circle
        // distinction is in how they are ASSIGNED, not how they are defined.
        setPermissionsTeamId(null);

        $guard = 'web';

        // Global roles — assigned without a team context.
        $globalRoles = [
            'new_user',
            'full_member',
            'curator',
            'trainer',
            'admin',
            'superadmin',
        ];

        // Circle roles — assigned within a circle (team) context.
        $circleRoles = [
            'circle_admin',
            'circle_full_member',
            'circle_visitor',
        ];

        foreach ([...$globalRoles, ...$circleRoles] as $role) {
            Role::findOrCreate($role, $guard);
        }

        // Global permissions (team-agnostic, circle_id = null).
        $permissions = [
            'edit_content_blocks',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        // Grant all defined permissions to superadmin.
        Role::findByName('superadmin', $guard)->givePermissionTo($permissions);

        $this->command->info(sprintf(
            'Seeded %d global roles, %d circle roles and %d permissions.',
            count($globalRoles),
            count($circleRoles),
            count($permissions),
        ));
    }
}
