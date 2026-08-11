<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceStatusService;

/** Disable a device — block all opens, keep history (adminDisableDevice, super_admin). */
final class DisableDevice
{
    public function __construct(private readonly DeviceStatusService $status) {}

    public function handle(Device $device, ?string $reason, AdminUser $admin): Device
    {
        return $this->status->disable($device, $reason, $admin);
    }
}
