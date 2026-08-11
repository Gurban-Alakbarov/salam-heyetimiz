<?php

namespace Database\Seeders\Rbac;

use Illuminate\Database\Seeder;

/** Runs the full RBAC seed: catalog → role grants → complexes → demo admins (order matters). */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            ComplexSeeder::class,
            AdminUserSeeder::class,
            PermissionOverrideSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
