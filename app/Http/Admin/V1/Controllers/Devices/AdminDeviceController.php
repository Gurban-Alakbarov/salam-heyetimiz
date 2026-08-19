<?php

namespace App\Http\Admin\V1\Controllers\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\DeviceComm\Exceptions\TraccarDeviceNotFoundException;
use App\Domain\DeviceComm\Services\TraccarReconciliationService;
use App\Domain\Devices\Actions\DecommissionDevice;
use App\Domain\Devices\Actions\DisableDevice;
use App\Domain\Devices\Actions\EnableDevice;
use App\Domain\Devices\Actions\RegisterDevice;
use App\Domain\Devices\Actions\SetDeviceGeofence;
use App\Domain\Devices\Actions\TransferDevice;
use App\Domain\Devices\Actions\UpdateDevice;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Queries\DeviceQuery;
use App\Domain\Devices\Services\DeviceImageService;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Http\Admin\V1\Requests\Devices\DecommissionDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\DisableDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\ReconcileDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\RegisterDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\TransferDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\UpdateDeviceRequest;
use App\Http\Admin\V1\Requests\Devices\UploadDeviceImageRequest;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Concerns\PresentsAdminDevice;
use App\Http\Resources\DeviceAdminDetailResource;
use App\Http\Resources\DeviceAdminResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminDeviceController
{
    use AuthorizesAdmin;
    use PresentsAdminDevice;

    public function __construct(private readonly SubscriptionQuery $subscriptions) {}

    /** GET /admin/v1/devices — adminListDevices (devices.view; complex_manager scoped to its complex). */
    public function index(Request $request, DeviceQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_VIEW);

        $result = $query->adminList(
            status: $request->query('status'),
            ownerUserId: $request->query('owner_user_id') !== null ? (int) $request->query('owner_user_id') : null,
            regionId: $request->query('region_id') !== null ? (int) $request->query('region_id') : null,
            q: $request->query('q'),
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
            complexId: $this->complexScopeId($request),
        );

        $result['data']->each(fn (Device $device) => $device->whitelist_used = $this->whitelistUsed((int) $device->getKey()));

        return response()->json([
            'data' => DeviceAdminResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** POST /admin/v1/devices — adminCreateDevice (devices.create). */
    public function store(RegisterDeviceRequest $request, RegisterDevice $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_CREATE);

        $device = $action->handle($request->toData(), $request->user());

        return $this->adminDevice($device)->response()->setStatusCode(201);
    }

    /**
     * POST /admin/v1/devices/reconcile — adminReconcileDevice (devices.reconcile).
     *
     * Adopts a device that already exists in Traccar (created directly in the Traccar UI) into Laravel:
     * creates the `devices` row + the `traccar_devices` mapping to the EXISTING Traccar id. Idempotent.
     */
    public function reconcile(ReconcileDeviceRequest $request, TraccarReconciliationService $service): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_RECONCILE);

        try {
            $result = $service->reconcile($request->toData(), $request->uniqueId(), $request->user());
        } catch (TraccarDeviceNotFoundException $e) {
            throw ValidationException::withMessages(['unique_id' => [$e->getMessage()]]);
        }

        return $this->adminDevice($result->device)->response()->setStatusCode($result->created ? 201 : 200);
    }

    /** GET /admin/v1/devices/{deviceId} — adminGetDevice (devices.view; complex-scoped). */
    public function show(Request $request, int $deviceId): DeviceAdminDetailResource
    {
        $this->requirePermission($request, Permission::DEVICES_VIEW);

        /**
         * `owner` / roster `user` are loaded WITH TRASHED on purpose: a resident deleted from the system
         * (residents.delete → soft delete) keeps their revoked roster row, and without withTrashed the
         * relation resolves to null → `users[].user: null` in the payload → the panel blows up. The row
         * stays visible (history) and carries `user.deleted = true` so the UI can mark it.
         *
         * @var Device $device
         */
        $device = Device::query()
            ->withTrashed()
            ->with([
                'model',
                'simOperator',
                'region',
                'owner' => fn ($q) => $q->withTrashed(),
                'deviceUsers.user' => fn ($q) => $q->withTrashed(),
            ])
            ->findOrFail($deviceId);

        $this->assertDeviceInScope($request, $device);

        $device->whitelist_used = $this->whitelistUsed($deviceId);

        $device->deviceUsers->each(function (DeviceUser $du): void {
            $du->subscription_brief = $this->subscriptions->forDeviceUser((int) $du->getKey());
        });

        return new DeviceAdminDetailResource($device);
    }

    /** PATCH /admin/v1/devices/{deviceId} — adminUpdateDevice (devices.update). */
    public function update(UpdateDeviceRequest $request, int $deviceId, UpdateDevice $action, SetDeviceGeofence $geofence): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_UPDATE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $device = $action->handle($device, $request->toUpdateArray(), $request->user());

        // GEOFENCE-1 — geofence goes through the shared action (so its radius/coords rule runs) AFTER
        // the other fields (e.g. a coordinate update in the same PATCH is seen). Effective values fall
        // back to the device's current state for a partial PATCH.
        if ($request->touchesGeofence()) {
            $v = $request->validated();
            $enabled = array_key_exists('geofence_enabled', $v)
                ? (bool) $v['geofence_enabled']
                : (bool) $device->geofence_enabled;
            $radius = array_key_exists('geofence_radius_m', $v)
                ? ($v['geofence_radius_m'] !== null ? (int) $v['geofence_radius_m'] : null)
                : $device->geofence_radius_m;
            $device = $geofence->handle($device, $enabled, $radius);
        }

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }

    /** POST /admin/v1/devices/{deviceId}/image — adminUploadDeviceImage (devices.update). Multipart. */
    public function uploadImage(UploadDeviceImageRequest $request, int $deviceId, DeviceImageService $images): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_UPDATE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $images->store($request->file('image'), $device);

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }

    /** DELETE /admin/v1/devices/{deviceId}/image — adminDeleteDeviceImage (devices.update). */
    public function deleteImage(Request $request, int $deviceId, DeviceImageService $images): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_UPDATE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $images->deleteFor($device);

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }

    /** DELETE /admin/v1/devices/{deviceId} — adminDecommissionDevice (devices.decommission). */
    public function decommission(DecommissionDeviceRequest $request, int $deviceId, DecommissionDevice $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_DECOMMISSION);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $action->handle($device, (string) $request->input('reason'), $request->user());

        return response()->json(null, 204);
    }

    /** POST /admin/v1/devices/{deviceId}/disable — adminDisableDevice (devices.disable). */
    public function disable(DisableDeviceRequest $request, int $deviceId, DisableDevice $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_DISABLE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $device = $action->handle($device, (string) $request->input('reason'), $request->user());

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }

    /** POST /admin/v1/devices/{deviceId}/enable — adminEnableDevice (devices.disable). */
    public function enable(Request $request, int $deviceId, EnableDevice $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_DISABLE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $device = $action->handle($device, $request->user());

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }

    /** POST /admin/v1/devices/{deviceId}/transfer — adminTransferDevice (devices.transfer). */
    public function transfer(TransferDeviceRequest $request, int $deviceId, TransferDevice $action): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_TRANSFER);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $device = $action->handle($device, $request->toData(), $request->user());

        return $this->adminDevice($device)->response()->setStatusCode(200);
    }
}
