<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\DTOs\DeviceRegistrationData;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceProvisioningService;

/** Register a new physical device (techRegisterDevice / adminCreateDevice). */
final class RegisterDevice
{
    public function __construct(private readonly DeviceProvisioningService $provisioning) {}

    public function handle(DeviceRegistrationData $data, ?AdminUser $admin): Device
    {
        return $this->provisioning->register($data, $admin);
    }
}
