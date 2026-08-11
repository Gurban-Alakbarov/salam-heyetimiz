<?php

namespace App\Http\Concerns;

use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Http\Resources\DeviceAdminResource;

/** Loads the relations + computed whitelist usage a DeviceAdmin payload needs (R-DOM-14). */
trait PresentsAdminDevice
{
    protected function adminDevice(Device $device): DeviceAdminResource
    {
        $device->loadMissing(['model', 'simOperator', 'region', 'owner']);
        $device->whitelist_used = $this->whitelistUsed((int) $device->getKey());

        return new DeviceAdminResource($device);
    }

    protected function whitelistUsed(int $deviceId): int
    {
        return DeviceUser::query()
            ->where('device_id', $deviceId)
            ->where('status', DeviceUserStatus::Active->value)
            ->count();
    }
}
