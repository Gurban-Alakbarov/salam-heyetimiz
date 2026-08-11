<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceStatusService;

/** Re-enable a disabled device (adminEnableDevice, super_admin). */
final class EnableDevice
{
    public function __construct(private readonly DeviceStatusService $status) {}

    public function handle(Device $device, AdminUser $admin): Device
    {
        return $this->status->enable($device, $admin);
    }
}
