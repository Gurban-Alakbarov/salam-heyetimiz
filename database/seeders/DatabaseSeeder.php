<?php

namespace Database\Seeders;

use Database\Seeders\Lookups\DeviceModelSeeder;
use Database\Seeders\Lookups\RegionSeeder;
use Database\Seeders\Lookups\SimOperatorSeeder;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;
use Database\Seeders\Rbac\RbacSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Lookups seeded after the 01_identity_lookups batch (DB Arch §11.1).
        $this->call([
            SimOperatorSeeder::class,
            DeviceModelSeeder::class,
            RegionSeeder::class,
        ]);

        // RBAC: permission catalog, role grants, demo complexes + admin accounts (08_rbac batch).
        $this->call([
            RbacSeeder::class,
        ]);

        // Notification templates + az/ru/en copy (11_notifications batch).
        $this->call([
            NotificationTemplatesSeeder::class,
        ]);
    }
}
