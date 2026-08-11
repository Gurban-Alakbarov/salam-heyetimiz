<?php

namespace App\Http\Api\V1\Controllers\Devices;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Queries\DeviceStatsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * getDeviceStats — aggregated open statistics for a device (DeviceComm-owned; reads open_commands).
 * Lives here rather than the Devices controller because the data source is the command history.
 */
class DeviceStatsController
{
    /** GET /v1/devices/{deviceId}/stats — getDeviceStats. */
    public function show(Request $request, int $deviceId, DeviceStatsQuery $query): JsonResponse
    {
        $device = Device::query()->findOrFail($deviceId);
        abort_unless($request->user()?->can('view', $device) ?? false, 404);

        $period = (string) $request->query('period', '30d');

        return response()->json($query->forDevice($deviceId, $period));
    }
}
