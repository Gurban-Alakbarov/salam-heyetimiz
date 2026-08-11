<?php

namespace App\Http\Admin\V1\Controllers\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Devices\Actions\AssignDeviceOwner;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Actions\AddDeviceMember;
use App\Domain\Roster\Actions\RemoveDeviceMember;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Exceptions\RosterMemberNotFoundException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Actions\GrantSubscription;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Http\Admin\V1\Requests\Devices\AddDeviceMemberRequest;
use App\Http\Admin\V1\Requests\Devices\AssignDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\GrantSubscriptionRequest;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Concerns\PresentsAdminDevice;
use App\Http\Resources\DeviceUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin barrier/resident assignment: bind an owner (assign), add/remove residents on the device roster,
 * and grant a resident an unpaid subscription. Permission-gated (devices.assign / residents.create /
 * residents.delete / subscriptions.manage) and complex-scoped: a complex_manager may only act on
 * devices in its own complex.
 */
class AdminDeviceRosterController
{
    use AuthorizesAdmin;
    use PresentsAdminDevice;

    /** POST /admin/v1/devices/{deviceId}/assign — adminAssignDevice (devices.assign). */
    public function assignOwner(AssignDeviceRequest $request, int $deviceId, AssignDeviceOwner $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_ASSIGN);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $device = $action->handle($device, $request->toData(), $request->user());

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }

    /** POST /admin/v1/devices/{deviceId}/users — adminAddDeviceUser (residents.create). */
    public function addUser(AddDeviceMemberRequest $request, int $deviceId, AddDeviceMember $action): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_CREATE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $member = $action->handle($device, $request->toData(), $request->user());
        $member->loadMissing('user');
        $member->subscription_brief = null;

        return (new DeviceUserResource($member))->response()->setStatusCode(201);
    }

    /** DELETE /admin/v1/devices/{deviceId}/users/{userId} — adminRemoveDeviceUser (residents.delete). */
    public function removeUser(Request $request, int $deviceId, int $userId, RemoveDeviceMember $action): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_DELETE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $action->handle($device, $userId, $request->user());

        return response()->json(null, 204);
    }

    /**
     * POST /admin/v1/devices/{deviceId}/users/{userId}/subscription — adminGrantSubscription
     * (subscriptions.manage). Activates (or extends) the resident's entitlement WITHOUT a payment.
     * Returns the refreshed roster row so the panel can render the new subscription state.
     */
    public function grantSubscription(
        GrantSubscriptionRequest $request,
        int $deviceId,
        int $userId,
        GrantSubscription $action,
        SubscriptionQuery $subscriptions,
    ): JsonResponse {
        $this->requirePermission($request, Permission::SUBSCRIPTIONS_MANAGE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $action->handle($device, $userId, $request->tier(), $request->termDays(), $request->user());

        /** @var DeviceUser|null $member */
        $member = DeviceUser::query()
            ->where('device_id', $deviceId)
            ->where('user_id', $userId)
            ->where('status', DeviceUserStatus::Active->value)
            ->first();

        if ($member === null) {
            throw new RosterMemberNotFoundException();
        }

        $member->loadMissing('user');
        $member->subscription_brief = $subscriptions->forDeviceUser((int) $member->getKey());

        return (new DeviceUserResource($member))->response()->setStatusCode(200);
    }
}
