<?php

namespace App\Http\Admin\V1\Controllers\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Services\FamilyService;
use App\Domain\Users\Models\User;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;

/**
 * POST /admin/v1/devices/{deviceId}/family-invitations — adminInviteFamilyMember (residents.create; complex-scoped).
 *
 * Owner decision 2026-10-29 (option a): an admin may invite a family member ON BEHALF OF the family head, exactly as
 * the head would from the app — the same FamilyService::invite rules (head rights on this device, limits, active
 * complex, BR-19 …), the same email to the invitee, who accepts it in the app; the member's own subscription then
 * follows the family rules. Only who pressed the button differs, and that is audited.
 */
final class AdminFamilyInvitationController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly FamilyService $family,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(Request $request, int $deviceId): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::RESIDENTS_CREATE);
        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $data = $request->validate([
            'head_user_id' => ['required', 'integer'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
        ]);
        /** @var User|null $head */
        $head = User::query()->find((int) $data['head_user_id']);
        if ($head === null || ! Gate::forUser($head)->allows('manageFamily', $device)) {
            return $this->error('not_family_head', 'Bu şəxs bu cihazda ailə başçısı deyil (şəxsi cihazda yalnız sahib, kompleks cihazında öz abunəliyi olan sakin ailə dəvət edə bilər).', 422);
        }

        try {
            $result = $this->family->invite($head, $device, $data['email'], $data['first_name'], $data['last_name']);
        } catch (InvitationException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->status);
        }

        $this->audit->record('family.invited_by_admin', [
            'device_id' => (int) $device->getKey(),
            'head_user_id' => (int) $head->getKey(),
            'admin_id' => (int) $admin->getKey(),
            'granted_now' => $result['granted'] !== null,
            'invitation_id' => $result['invitation']?->getKey(),
        ], Device::class, (int) $device->getKey());

        return response()->json(['data' => [
            // an existing account may be granted at once (same as in the app); otherwise an invitation e-mail goes out
            'granted' => $result['granted'] !== null,
            'invitation_id' => $result['invitation']?->getKey(),
        ]], 201);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        $requestId = Context::get('request_id');

        return response()->json(['error' => [
            'code' => $code, 'message_key' => 'errors.'.$code, 'message' => $message, 'details' => null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]], $status);
    }
}
