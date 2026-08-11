<?php

namespace App\Http\Admin\V1\Controllers\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\DeviceComm\Actions\DispatchRelayTest;
use App\Domain\DeviceComm\Actions\ResyncWhitelist;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Queries\DeviceDiagnosticQuery;
use App\Domain\DeviceComm\Queries\OpenCommandQuery;
use App\Domain\DeviceComm\Queries\WhitelistChangeQuery;
use App\Domain\Devices\Models\Device;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\DeviceDiagnosticResource;
use App\Http\Resources\OpenCommandResource;
use App\Http\Resources\WhitelistChangeResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin command history, whitelist queue inspection, and forced resync (DeviceComm core). */
class AdminDeviceCommController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/devices/{deviceId}/commands — adminDeviceCommands (commands.view). */
    public function commands(Request $request, int $deviceId, OpenCommandQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::COMMANDS_VIEW);
        $device = Device::query()->withTrashed()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $result = $query->adminForDevice(
            deviceId: $deviceId,
            state: $request->query('state'),
            since: $request->query('since'),
            until: $request->query('until'),
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => OpenCommandResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** GET /admin/v1/devices/{deviceId}/whitelist-queue — adminWhitelistQueue (whitelist.view). */
    public function whitelistQueue(Request $request, int $deviceId, WhitelistChangeQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::WHITELIST_VIEW);
        $device = Device::query()->withTrashed()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $result = $query->forDevice(
            deviceId: $deviceId,
            status: $request->query('status'),
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => WhitelistChangeResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** GET /admin/v1/devices/{deviceId}/diagnostics — adminDeviceDiagnostics (devices.diagnostics.view). */
    public function diagnostics(Request $request, int $deviceId, DeviceDiagnosticQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_DIAGNOSTICS_VIEW);
        $device = Device::query()->withTrashed()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $result = $query->forDevice(
            deviceId: $deviceId,
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => DeviceDiagnosticResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** POST /admin/v1/devices/{deviceId}/whitelist/resync — adminResyncWhitelist (whitelist.manage, 202). */
    public function resync(Request $request, int $deviceId, ResyncWhitelist $action): JsonResponse
    {
        $this->requirePermission($request, Permission::WHITELIST_MANAGE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);
        $action->handle($device, $request->user());

        return response()->json(null, 202);
    }

    /**
     * POST /admin/v1/devices/{deviceId}/relay — adminRelayTest (commands.test, 202).
     *
     * Fires an open/close relay command through the DeviceComm pipeline (source=admin). Returns the
     * queued command; the caller polls GET .../commands to watch it reach opened/failed with the
     * device's terminal response text.
     */
    public function relay(Request $request, int $deviceId, DispatchRelayTest $action): JsonResponse
    {
        $this->requirePermission($request, Permission::COMMANDS_TEST);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:open,close'],
        ]);

        $command = $action->handle($device, CommandDirection::from($validated['action']), $request->ip());

        return response()->json(new OpenCommandResource($command), 202);
    }
}
