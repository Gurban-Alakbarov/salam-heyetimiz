<?php

namespace App\Http\Admin\V1\Controllers\Residents;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Roster\Actions\DeleteResident;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Queries\ResidentQuery;
use App\Domain\Users\Models\User;
use App\Domain\Users\Services\AccountEmailService;
use App\Http\Concerns\AuthorizesAdmin;
use App\Support\Pagination\Cursor;
use App\Support\Phone\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Residents directory (residents.view): every resident with the complex they live in, their device's
 * status/connectivity, role and subscription status. complex_manager is scoped to its own complex.
 * Also owns account removal from the system (residents.delete).
 */
class AdminResidentController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/residents — adminListResidents (residents.view; complex_manager scoped). */
    public function index(Request $request, ResidentQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_VIEW);

        $offlineMinutes = (int) config('domain.devices.offline_threshold_minutes', 15);
        $threshold = now()->subMinutes($offlineMinutes);

        $result = $query->adminList(
            complexId: $request->query('complex_id') !== null ? (int) $request->query('complex_id') : null,
            q: $request->query('q'),
            role: $request->query('role'),
            status: $request->query('subscription_status'),
            scopeComplexId: $this->complexScopeId($request),
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        $data = array_map(function (array|object $r) use ($threshold): array {
            $r = (object) $r;
            $online = $r->last_online_at !== null && Carbon::parse($r->last_online_at)->greaterThan($threshold);

            return [
                'device_user_id' => (int) $r->device_user_id,
                'user_id' => (int) $r->user_id,
                'phone_masked' => PhoneNumber::tryFromInput($r->phone)?->masked() ?? $r->phone,
                // Full email (admin surface only) — the account identifier admins search/act on.
                'email' => $r->email,
                // K1: false = the owner has not proven this email yet (admin may still record / correct it).
                'email_verified' => $r->email_verified_at !== null,
                'full_name' => $r->full_name,
                'role' => $r->role,
                'last_open_at' => $r->last_open_at !== null ? Carbon::parse($r->last_open_at)->toIso8601String() : null,
                'complex' => $r->complex_id !== null ? ['id' => (int) $r->complex_id, 'name' => $r->complex_name] : null,
                'device' => [
                    'id' => (int) $r->device_id,
                    'serial' => $r->serial,
                    'status' => $r->device_status,
                    'online' => $online,
                ],
                'subscription' => $r->sub_status !== null
                    ? ['status' => $r->sub_status, 'ends_at' => $r->sub_ends_at !== null ? Carbon::parse($r->sub_ends_at)->toIso8601String() : null]
                    : null,
            ];
        }, $result['data']);

        return response()->json(['data' => $data, 'page' => $result['page']]);
    }

    /**
     * DELETE /admin/v1/residents/{userId} — adminDeleteResident (residents.delete).
     *
     * Removes the resident from the SYSTEM: every roster membership is revoked (each device is
     * de-whitelisted), entitlements are cancelled, sessions killed, account soft-deleted. Refuses with
     * 409 while the resident still owns a device. A complex_manager may only delete a resident whose
     * active memberships all sit inside its own complex.
     */
    public function destroy(Request $request, int $userId, DeleteResident $action): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_DELETE);

        /** @var User $user */
        $user = User::query()->findOrFail($userId);
        $this->assertResidentInScope($request, $userId);

        $action->handle($user, $request->user());

        return response()->json(null, 204);
    }

    /**
     * PUT /admin/v1/residents/{userId}/email — adminSetResidentEmail (residents.update).
     *
     * K1: record the owner's email on a not-yet-verified account (e.g. created by phone at device
     * assignment). The owner then registers with this email and proves it with the email OTP — a phone
     * number alone never hands over an account with live access. The email stays unverified here; a
     * verified email is the login identity and is never replaced (409 email_already_verified); an email
     * of another account answers 409 email_taken.
     */
    public function setEmail(Request $request, int $userId, AccountEmailService $emails): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_UPDATE);
        $email = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:160']])['email'];

        /** @var User $user */
        $user = User::query()->findOrFail($userId);
        $this->assertResidentInScope($request, $userId);

        $user = $emails->record($user, $email);

        return response()->json([
            'user_id' => (int) $user->getKey(),
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
        ]);
    }

    /** complex_manager: only a resident whose active memberships all sit inside its own complex (else 403). */
    private function assertResidentInScope(Request $request, int $userId): void
    {
        $scopeComplexId = $this->complexScopeId($request);
        if ($scopeComplexId === null) {
            return;
        }

        $outsideScope = DeviceUser::query()
            ->join('devices', 'devices.id', '=', 'device_users.device_id')
            ->where('device_users.user_id', $userId)
            ->where('device_users.status', DeviceUserStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('devices.complex_id')->orWhere('devices.complex_id', '!=', $scopeComplexId))
            ->exists();

        abort_if($outsideScope, 403);
    }
}
