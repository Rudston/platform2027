<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // superadmin is a global role — assign it with no circle (team) context.
        setPermissionsTeamId(null);

        $user = User::updateOrCreate(
            ['email' => 'rudston@mobilize.org.za'],
            [
                'name' => 'Rudston',
                'country_code' => '27',
                'mobile' => '0660775046',
                // Cast 'password' => 'hashed' on the User model hashes this on set.
                'password' => 'pwd',
            ],
        );

        $user->assignRole('superadmin');

        $this->command->info("Admin user {$user->email} created and assigned superadmin.");
    }
}
