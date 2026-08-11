<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceStatusService;

/** Decommission a device — terminal soft-delete + async roster revocation (adminDecommissionDevice). */
final class DecommissionDevice
{
    public function __construct(private readonly DeviceStatusService $status) {}

    public function handle(Device $device, string $reason, AdminUser $admin): void
    {
        $this->status->decommission($device, $reason, $admin);
    }
}
