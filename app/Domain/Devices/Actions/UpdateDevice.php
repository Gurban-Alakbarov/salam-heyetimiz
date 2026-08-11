<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceProvisioningService;

/** Apply an admin's partial device edit (adminUpdateDevice). */
final class UpdateDevice
{
    public function __construct(private readonly DeviceProvisioningService $provisioning) {}

    /**
     * @param  array<string, mixed>  $changes  mapped DB columns, present keys only
     */
    public function handle(Device $device, array $changes, AdminUser $admin): Device
    {
        return $this->provisioning->update($device, $changes, $admin);
    }
}
