<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Authorization\Permission as PermissionCatalog;
use App\Domain\Admin\Models\Permission;
use Illuminate\Database\Seeder;

/** Seeds the permission catalog (idempotent). Source of truth: App\Domain\Admin\Authorization\Permission. */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::definitions() as $key => $def) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['group' => $def['group'], 'label' => $def['label']],
            );
        }
    }
}
