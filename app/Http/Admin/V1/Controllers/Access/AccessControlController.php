<?php

namespace App\Http\Admin\V1\Controllers\Access;

use App\Domain\Admin\Authorization\Permission as Catalog;
use App\Domain\Admin\Authorization\RolePermissionMatrix;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\RolePermission;
use App\Domain\Admin\Models\UserPermission;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Dynamic access control (access.manage — super_admin tier): the permission catalog, the role templates,
 * and per-admin overrides. Effective = (role defaults ∪ granted) \ revoked, resolved by AdminUser. Roles
 * are templates only; the real authorization layer is the effective permission set.
 */
class AccessControlController
{
    use AuthorizesAdmin;

    private const ROLE_DESCRIPTIONS = [
        'super_admin' => 'Tam giriş — sistem, adminlər, hər kompleks və hər əməliyyat.',
        'technical' => 'Cihaz quraşdırma/idarə: yarat/redaktə/reconcile, diaqnostika, whitelist, açılış tarixçəsi.',
        'operator' => 'Sakinlər, mənzillər, sahib/barrier təyini, whitelist idarəetməsi.',
        'finance' => 'Ödənişlər, sifarişlər, geri qaytarmalar, abunəliklər, qaimələr, hesabatlar.',
        'support' => 'Yalnız oxuma: sakin/sifariş/abunəlik/cihaz statusu + OTP yenidən göndərmə.',
        'complex_manager' => 'Yalnız öz kompleksi: sakinlər, barrierlər, whitelist, hesabatlar.',
    ];

    /** GET /admin/v1/access/roles — adminListRoles (access.manage). */
    public function roles(Request $request): JsonResponse
    {
        $this->requirePermission($request, Catalog::ACCESS_MANAGE);

        $data = [];
        foreach (AdminRole::cases() as $role) {
            $data[] = [
                'role' => $role->value,
                'label' => $role->label(),
                'description' => self::ROLE_DESCRIPTIONS[$role->value] ?? '',
                'is_super' => $role->isSuperAdmin(),
                'is_complex_scoped' => $role->isComplexScoped(),
                'admin_count' => AdminUser::query()->where('role', $role->value)->count(),
                'default_permissions' => $this->roleDefaults($role),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /** GET /admin/v1/access/permissions — adminListPermissions (access.manage). Grouped by module. */
    public function permissions(Request $request): JsonResponse
    {
        $this->requirePermission($request, Catalog::ACCESS_MANAGE);

        $groups = [];
        foreach (Catalog::definitions() as $key => $def) {
            $groups[$def['group']][] = ['key' => $key, 'label' => $def['label']];
        }

        return response()->json([
            'data' => collect($groups)->map(fn (array $perms, string $group): array => [
                'group' => $group,
                'permissions' => $perms,
            ])->values()->all(),
        ]);
    }

    /** PATCH /admin/v1/access/roles/{role} — adminUpdateRolePermissions (access.manage). Edits the template. */
    public function updateRole(Request $request, string $role): JsonResponse
    {
        $this->requirePermission($request, Catalog::ACCESS_MANAGE);

        $roleEnum = AdminRole::tryFrom($role);
        if ($roleEnum === null) {
            abort(404);
        }
        if ($roleEnum->isSuperAdmin()) {
            throw ValidationException::withMessages(['role' => 'Super admin şablonu dəyişdirilə bilməz (həmişə tam giriş).']);
        }

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', Catalog::all())],
        ]);
        $wantKeys = array_values(array_unique($validated['permissions']));
        $idByKey = Permission::query()->pluck('id', 'key')->all();
        $wantIds = array_values(array_filter(array_map(fn (string $k): ?int => isset($idByKey[$k]) ? (int) $idByKey[$k] : null, $wantKeys)));

        foreach ($wantIds as $pid) {
            RolePermission::query()->updateOrCreate(['role' => $role, 'permission_id' => $pid], []);
        }
        RolePermission::query()->where('role', $role)
            ->when($wantIds !== [], fn ($q) => $q->whereNotIn('permission_id', $wantIds))
            ->delete();

        app(AuditLogger::class)->record('access.role_updated', ['role' => $role, 'permissions' => $wantKeys]);

        return response()->json(['role' => $role, 'default_permissions' => $this->roleDefaults($roleEnum)]);
    }

    /** GET /admin/v1/admins/{adminId}/permissions — adminGetUserPermissions (access.manage). */
    public function userPermissions(Request $request, int $adminId): JsonResponse
    {
        $this->requirePermission($request, Catalog::ACCESS_MANAGE);

        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($adminId);

        return response()->json($this->userPayload($admin));
    }

    /** POST /admin/v1/admins/{adminId}/permissions/grant — adminGrantPermission (access.manage). */
    public function grant(Request $request, int $adminId): JsonResponse
    {
        return $this->override($request, $adminId, 'grant');
    }

    /** POST /admin/v1/admins/{adminId}/permissions/revoke — adminRevokePermission (access.manage). */
    public function revoke(Request $request, int $adminId): JsonResponse
    {
        return $this->override($request, $adminId, 'revoke');
    }

    /** POST /admin/v1/admins/{adminId}/permissions/reset — adminResetPermissions (access.manage). */
    public function reset(Request $request, int $adminId): JsonResponse
    {
        $actor = $this->requirePermission($request, Catalog::ACCESS_MANAGE);
        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($adminId);

        UserPermission::query()->where('admin_user_id', $admin->getKey())->delete();
        app(AuditLogger::class)->record('access.permissions_reset', ['admin_id' => $admin->getKey()], AdminUser::class, (int) $admin->getKey());

        return response()->json($this->userPayload($admin->refresh()));
    }

    private function override(Request $request, int $adminId, string $effect): JsonResponse
    {
        $actor = $this->requirePermission($request, Catalog::ACCESS_MANAGE);
        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($adminId);

        if ($admin->isSuperAdmin()) {
            throw ValidationException::withMessages(['admin' => 'Super admin icazələri dəyişdirilə bilməz (həmişə tam giriş).']);
        }

        $validated = $request->validate([
            'permission' => ['required', 'string', 'in:'.implode(',', Catalog::all())],
        ]);
        $permissionId = (int) Permission::query()->where('key', $validated['permission'])->value('id');
        if ($permissionId === 0) {
            abort(404);
        }

        UserPermission::query()->updateOrCreate(
            ['admin_user_id' => $admin->getKey(), 'permission_id' => $permissionId],
            ['effect' => $effect, 'granted_by_admin_id' => $actor->getKey()],
        );

        app(AuditLogger::class)->record(
            'access.permission_'.$effect,
            ['permission' => $validated['permission']],
            AdminUser::class,
            (int) $admin->getKey(),
        );

        return response()->json($this->userPayload($admin->refresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(AdminUser $admin): array
    {
        return [
            'admin' => ['id' => (int) $admin->id, 'email' => $admin->email, 'name' => $admin->name, 'role' => $admin->role->value],
            'inherited' => $admin->roleDefaultKeys(),
            'additional' => $admin->grantedKeys(),
            'revoked' => $admin->revokedKeys(),
            'effective' => $admin->permissionKeys(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function roleDefaults(AdminRole $role): array
    {
        if ($role->isSuperAdmin()) {
            return Catalog::all();
        }
        $keys = RolePermission::query()->where('role_permissions.role', $role->value)
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->pluck('permissions.key')->all();

        return $keys === [] ? RolePermissionMatrix::forRole($role) : $keys;
    }
}
