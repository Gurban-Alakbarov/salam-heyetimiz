<?php

namespace App\Http\Admin\V1\Controllers\Admins;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Support\RecoveryCodes;
use App\Domain\Auth\Support\Totp;
use App\Http\Admin\V1\Requests\Admins\CreateAdminRequest;
use App\Http\Admin\V1\Requests\Admins\UpdateAdminRequest;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admin account lifecycle (RBAC): create/list/update/deactivate admins and assign roles. super_admin-tier. */
class AdminManagementController
{
    use AuthorizesAdmin;

    public function __construct(private readonly AuditLogger $audit) {}

    /** GET /admin/v1/admins — adminListAdmins (admins.view). */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::ADMINS_VIEW);

        $admins = AdminUser::query()->orderBy('id')->limit(200)->get();

        return response()->json([
            'data' => AdminUserResource::collection($admins),
        ]);
    }

    /** POST /admin/v1/admins — adminCreateAdmin (admins.create). Returns the 2FA bootstrap secret once. */
    public function store(CreateAdminRequest $request): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::ADMINS_CREATE);
        $v = $request->validated();

        $secret = Totp::fromConfig()->generateSecret(10);
        $recovery = RecoveryCodes::generate(
            (int) config('domain.auth.recovery_codes.count'),
            (int) config('domain.auth.recovery_codes.length_bytes'),
        );

        /** @var AdminUser $admin */
        $admin = AdminUser::query()->create([
            'email' => $v['email'],
            'name' => $v['name'],
            'password' => $v['password'],
            'role' => $v['role'],
            'complex_id' => $v['complex_id'] ?? null,
            'status' => 'active',
            'preferred_language' => 'az',
            'is_2fa_enabled' => true,
            'totp_secret' => $secret,
            'recovery_codes_hashes' => $recovery['hashes'],
            'recovery_codes_generated_at' => now(),
            'password_changed_at' => now(),
            'created_by_admin_id' => $actor->getKey(),
        ]);

        $this->audit->record('admin.created', ['email' => $admin->email, 'role' => $admin->role->value], AdminUser::class, (int) $admin->getKey());

        return response()->json([
            'admin' => (new AdminUserResource($admin))->toArray($request),
            'totp_secret' => $secret,
            'recovery_codes' => $recovery['plaintext'],
        ], 201);
    }

    /** PATCH /admin/v1/admins/{adminId} — adminUpdateAdmin (admins.update; role change needs admins.assign_roles). */
    public function update(UpdateAdminRequest $request, int $adminId): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::ADMINS_UPDATE);

        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($adminId);
        $v = $request->validated();

        if (array_key_exists('role', $v) && $v['role'] !== $admin->role->value) {
            $this->requirePermission($request, Permission::ADMINS_ASSIGN_ROLES);
            $this->guardLastSuperAdmin($admin); // demoting the last super_admin is blocked
        }

        $changes = array_intersect_key($v, array_flip(['name', 'role', 'complex_id', 'status']));
        $admin->forceFill(array_merge($changes, ['updated_by_admin_id' => $actor->getKey()]))->save();

        $this->audit->record('admin.updated', ['changes' => $changes], AdminUser::class, (int) $admin->getKey());

        return response()->json((new AdminUserResource($admin->refresh()))->toArray($request), 200);
    }

    /** DELETE /admin/v1/admins/{adminId} — adminDeactivateAdmin (admins.delete). Soft offboard, never hard-delete. */
    public function destroy(Request $request, int $adminId): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::ADMINS_DELETE);

        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($adminId);

        if ((int) $admin->getKey() === (int) $actor->getKey()) {
            throw ValidationException::withMessages(['admin' => 'Öz hesabınızı deaktiv edə bilməzsiniz.']);
        }
        $this->guardLastSuperAdmin($admin);

        $admin->forceFill(['status' => 'offboarded', 'updated_by_admin_id' => $actor->getKey()])->save();

        $this->audit->record('admin.deactivated', ['email' => $admin->email], AdminUser::class, (int) $admin->getKey());

        return response()->json(null, 204);
    }

    private function guardLastSuperAdmin(AdminUser $admin): void
    {
        if ($admin->role !== AdminRole::SuperAdmin) {
            return;
        }
        $activeSupers = AdminUser::query()
            ->where('role', AdminRole::SuperAdmin->value)
            ->where('status', 'active')
            ->count();
        if ($activeSupers <= 1) {
            throw ValidationException::withMessages(['admin' => 'Son aktiv super admini dəyişmək/deaktiv etmək olmaz.']);
        }
    }
}
