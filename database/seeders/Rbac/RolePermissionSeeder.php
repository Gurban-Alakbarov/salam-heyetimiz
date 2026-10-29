<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Authorization\RolePermissionMatrix;
use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds role → permission DEFAULTS from the matrix (idempotent, re-run on every deploy). super_admin is implicit
 * (all) and is not stored. Panel edits to role templates (AccessControlController::updateRole) survive deploys:
 * the seeder remembers in `role_permission_seeds` which defaults it has already applied and
 *  - adds a matrix default only the FIRST time it sees it (a new permission or a new default reaches the role
 *    once; one an admin later removed in the panel is not re-added),
 *  - removes a grant only when the code dropped that default from the matrix (an admin-added grant that never
 *    came from the matrix is left alone).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $idByKey = Permission::query()->pluck('id', 'key')->all();
        $now = now();

        foreach (RolePermissionMatrix::map() as $role => $keys) {
            $wantIds = [];
            foreach ($keys as $key) {
                if (isset($idByKey[$key])) { // else PermissionSeeder must run first
                    $wantIds[] = (int) $idByKey[$key];
                }
            }

            $seededIds = DB::table('role_permission_seeds')->where('role', $role)
                ->pluck('permission_id')->map(fn ($id): int => (int) $id)->all();

            foreach (array_diff($wantIds, $seededIds) as $permissionId) {
                RolePermission::query()->updateOrCreate(['role' => $role, 'permission_id' => $permissionId], []);
                DB::table('role_permission_seeds')->insertOrIgnore([
                    'role' => $role,
                    'permission_id' => $permissionId,
                    'seeded_at' => $now,
                ]);
            }

            // Defaults the matrix no longer has: withdraw what the seeder once granted.
            $dropped = array_values(array_diff($seededIds, $wantIds));
            if ($dropped !== []) {
                RolePermission::query()->where('role', $role)->whereIn('permission_id', $dropped)->delete();
                DB::table('role_permission_seeds')->where('role', $role)->whereIn('permission_id', $dropped)->delete();
            }
        }
    }
}
