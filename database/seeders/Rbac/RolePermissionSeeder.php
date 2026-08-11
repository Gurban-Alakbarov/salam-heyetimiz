<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Authorization\RolePermissionMatrix;
use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\RolePermission;
use Illuminate\Database\Seeder;

/**
 * Seeds role → permission grants from the matrix (idempotent). super_admin is implicit (all) and is not
 * stored. Re-running reconciles: it adds new grants and removes grants no longer in the matrix, so the DB
 * always mirrors the documented matrix.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $idByKey = Permission::query()->pluck('id', 'key')->all();

        foreach (RolePermissionMatrix::map() as $role => $keys) {
            $wantIds = [];
            foreach ($keys as $key) {
                if (! isset($idByKey[$key])) {
                    continue; // permission not seeded yet — PermissionSeeder must run first
                }
                $permissionId = (int) $idByKey[$key];
                $wantIds[] = $permissionId;
                RolePermission::query()->updateOrCreate(
                    ['role' => $role, 'permission_id' => $permissionId],
                    [],
                );
            }

            // Drop grants that are no longer in the matrix for this role.
            RolePermission::query()
                ->where('role', $role)
                ->when($wantIds !== [], fn ($q) => $q->whereNotIn('permission_id', $wantIds))
                ->delete();
        }
    }
}
