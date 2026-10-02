<?php

namespace App\Http\Admin\V1\Controllers\Admins;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

/**
 * Link / unlink the mobile account a Komendant signs in with (IMPLEMENTATION_PLAN §12 / B5, BR-15).
 * Only a complex_manager can be linked; the mobile account must already exist with a VERIFIED email (it is
 * created by the normal email-OTP registration). One mobile account per admin. admins.update, audited.
 */
class AdminMobileUserController
{
    use AuthorizesAdmin;

    public function __construct(private readonly AuditLogger $audit) {}

    /** POST /admin/v1/admins/{adminId}/mobile-user {email} */
    public function link(Request $request, int $adminId): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::ADMINS_UPDATE);
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:160']]);

        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($adminId);
        if ($admin->role !== AdminRole::ComplexManager) {
            return $this->error(422, 'not_complex_manager', 'Yalnız kompleks meneceri (komendant) mobil hesaba bağlana bilər.');
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])
            ->whereNotNull('email_verified_at')
            ->where('status', UserStatus::Active->value)
            ->first();
        if ($user === null) {
            return $this->error(422, 'mobile_user_not_found', 'Bu email ilə təsdiqlənmiş aktiv mobil hesab tapılmadı.');
        }

        $taken = AdminUser::query()->where('user_id', $user->getKey())->whereKeyNot($admin->getKey())->exists();
        if ($taken) {
            return $this->error(409, 'mobile_user_already_linked', 'Bu mobil hesab artıq başqa adminə bağlıdır.');
        }

        $previous = $admin->user_id;
        $admin->forceFill(['user_id' => $user->getKey(), 'updated_by_admin_id' => $actor->getKey()])->save();
        $this->audit->record('admin.mobile_user_linked', [
            'admin_id' => (int) $admin->id, 'user_id' => (int) $user->id, 'previous_user_id' => $previous,
        ], AdminUser::class, (int) $admin->id);

        return response()->json(['data' => $this->payload($admin->refresh(), $user)]);
    }

    /** DELETE /admin/v1/admins/{adminId}/mobile-user */
    public function unlink(Request $request, int $adminId): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::ADMINS_UPDATE);
        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($adminId);

        $previous = $admin->user_id;
        $admin->forceFill(['user_id' => null, 'updated_by_admin_id' => $actor->getKey()])->save();
        $this->audit->record('admin.mobile_user_unlinked', ['admin_id' => (int) $admin->id, 'previous_user_id' => $previous], AdminUser::class, (int) $admin->id);

        return response()->json(['data' => $this->payload($admin->refresh(), null)]);
    }

    /** @return array<string, mixed> */
    private function payload(AdminUser $admin, ?User $user): array
    {
        return [
            'admin_id' => (int) $admin->id,
            'role' => $admin->role->value,
            'complex_id' => $admin->complex_id !== null ? (int) $admin->complex_id : null,
            'mobile_user' => $user !== null ? ['id' => (int) $user->id, 'email' => $user->email, 'full_name' => $user->full_name] : null,
        ];
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        $requestId = Context::get('request_id');

        return response()->json(['error' => [
            'code' => $code, 'message_key' => 'errors.'.$code, 'message' => $message, 'details' => null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]], $status);
    }
}
