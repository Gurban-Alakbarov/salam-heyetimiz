<?php

use App\Domain\Admin\Authorization\RolePermissionMatrix;
use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\RolePermission;
use Database\Seeders\Rbac\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

/*
| Role templates edited in the panel survive a deploy: the deploy re-runs RolePermissionSeeder, which must only add
| defaults it has never applied and must never undo an admin's panel change.
*/

function roleKeys(string $role): array
{
    return RolePermission::query()->where('role', $role)->with('permission')->get()
        ->map(fn (RolePermission $g): string => $g->permission->key)->sort()->values()->all();
}

beforeEach(fn () => seedRbac());

it('keeps a default the admin removed in the panel removed after the seeder runs again', function () {
    $keys = RolePermissionMatrix::map()['support'];
    $removed = $keys[0];

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/access/roles/support', ['permissions' => array_values(array_slice($keys, 1))])
        ->assertOk();

    (new RolePermissionSeeder)->run();

    expect(roleKeys('support'))->not->toContain($removed);
});

it('keeps a permission the admin added in the panel after the seeder runs again', function () {
    $keys = RolePermissionMatrix::map()['support'];
    $extra = collect(Permission::query()->pluck('key'))->first(fn (string $k): bool => ! in_array($k, $keys, true));

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/access/roles/support', ['permissions' => [...$keys, $extra]])
        ->assertOk();

    (new RolePermissionSeeder)->run();

    expect(roleKeys('support'))->toContain($extra);
});

it('still delivers a default it has never applied (e.g. a new permission) once', function () {
    $key = RolePermissionMatrix::map()['support'][0];
    $id = (int) Permission::query()->where('key', $key)->value('id');
    // Simulate a default the seeder has not applied yet.
    RolePermission::query()->where('role', 'support')->where('permission_id', $id)->delete();
    DB::table('role_permission_seeds')->where('role', 'support')->where('permission_id', $id)->delete();

    (new RolePermissionSeeder)->run();

    expect(roleKeys('support'))->toContain($key);
});
