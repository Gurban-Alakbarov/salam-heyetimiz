<?php

namespace App\Domain\DeviceComm\Listeners;

use App\Domain\Devices\Events\DeviceDecommissioned;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Services\WhitelistService;

/**
 * When a device is decommissioned, enqueue a whitelist clear so the physical unit ends up with no
 * authorised numbers once the drain runs (R-GSM-07). The device row is soft-deleted, so it is loaded
 * withTrashed.
 */
class ClearWhitelistOnDeviceDecommissioned
{
    public function __construct(private readonly WhitelistService $whitelist) {}

    public function handle(DeviceDecommissioned $event): void
    {
        $device = Device::withTrashed()->find($event->deviceId);
        if ($device === null) {
            return;
        }

        $this->whitelist->enqueue($device, WhitelistAction::Clear, WhitelistService::CLEAR_MARKER);
    }
}
