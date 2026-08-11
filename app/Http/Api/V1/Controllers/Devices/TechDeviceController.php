<?php

namespace App\Http\Api\V1\Controllers\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Devices\Actions\AssignDeviceOwner;
use App\Domain\Devices\Actions\RegisterDevice;
use App\Domain\Devices\Models\Device;
use App\Http\Admin\V1\Requests\Devices\AssignDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\RegisterDeviceRequest;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Concerns\PresentsAdminDevice;
use Illuminate\Http\JsonResponse;

/**
 * Technical mobile mode (admin-role users in the mobile app, admin JWT). Register + assign physical
 * devices in the field. Routes live on the mobile host under /v1/technical/* but require an admin token
 * with the matching permission (devices.create / devices.assign).
 */
class TechDeviceController
{
    use AuthorizesAdmin;
    use PresentsAdminDevice;

    /** POST /v1/technical/devices — techRegisterDevice (devices.create). */
    public function register(RegisterDeviceRequest $request, RegisterDevice $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_CREATE);

        $device = $action->handle($request->toData(), $request->user());

        return $this->adminDevice($device)->response()->setStatusCode(201);
    }

    /** POST /v1/technical/devices/{deviceId}/assign — techAssignDevice (devices.assign). */
    public function assign(AssignDeviceRequest $request, int $deviceId, AssignDeviceOwner $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_ASSIGN);

        $device = Device::query()->findOrFail($deviceId);
        $device = $action->handle($device, $request->toData(), $request->user());

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }
}
