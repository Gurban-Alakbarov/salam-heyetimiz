<?php

namespace App\Http\Admin\V1\Controllers\Admins;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Services\JwtService;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Impersonation: a super_admin (admins.impersonate) gets a token that acts as another admin — the token's
 * subject is the target, so every permission/scope check applies to the target — carrying an `impersonator`
 * claim. Stop returns a fresh token for the original admin. Both transitions are audited.
 */
class ImpersonationController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly AuditLogger $audit,
    ) {}

    /** POST /admin/v1/admins/{adminId}/impersonate — adminImpersonate (admins.impersonate). */
    public function start(Request $request, int $adminId): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::ADMINS_IMPERSONATE);

        if ($request->attributes->get('jwt_impersonator_id') !== null) {
            throw ValidationException::withMessages(['impersonation' => 'Artıq impersonation rejimindəsiniz.']);
        }

        /** @var AdminUser $target */
        $target = AdminUser::query()->findOrFail($adminId);

        if ((int) $target->getKey() === (int) $actor->getKey()) {
            throw ValidationException::withMessages(['impersonation' => 'Özünüzü impersonate edə bilməzsiniz.']);
        }
        if ($target->status !== AdminStatus::Active) {
            throw ValidationException::withMessages(['impersonation' => 'Yalnız aktiv admin impersonate edilə bilər.']);
        }

        $issued = $this->jwt->issueAdminAccessToken((int) $target->getKey(), $target->role->value, (int) $actor->getKey());

        $this->audit->record(
            'admin.impersonation.start',
            ['target_admin_id' => (int) $target->getKey(), 'target_email' => $target->email],
            AdminUser::class,
            (int) $target->getKey(),
        );

        return response()->json([
            'access_token' => $issued->token,
            'token_type' => 'Bearer',
            'expires_in' => $issued->expiresIn,
            'admin' => array_merge(
                (new AdminUserResource($target))->toArray($request),
                ['impersonator_admin_id' => (int) $actor->getKey()],
            ),
        ], 200);
    }

    /** POST /admin/v1/auth/stop-impersonation — adminStopImpersonation (any impersonating session). */
    public function stop(Request $request): JsonResponse
    {
        $impersonatorId = $request->attributes->get('jwt_impersonator_id');
        if (! is_numeric($impersonatorId)) {
            throw ValidationException::withMessages(['impersonation' => 'Impersonation rejimində deyilsiniz.']);
        }

        /** @var AdminUser|null $impersonator */
        $impersonator = AdminUser::query()->find((int) $impersonatorId);
        if ($impersonator === null || $impersonator->status !== AdminStatus::Active) {
            throw ValidationException::withMessages(['impersonation' => 'Orijinal admin hesabı əlçatan deyil.']);
        }

        $issued = $this->jwt->issueAdminAccessToken((int) $impersonator->getKey(), $impersonator->role->value);

        $this->audit->record('admin.impersonation.stop', ['returned_to' => $impersonator->email]);

        return response()->json([
            'access_token' => $issued->token,
            'token_type' => 'Bearer',
            'expires_in' => $issued->expiresIn,
            'admin' => (new AdminUserResource($impersonator))->toArray($request),
        ], 200);
    }
}
