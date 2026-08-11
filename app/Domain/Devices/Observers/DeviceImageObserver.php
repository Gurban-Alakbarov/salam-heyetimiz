<?php

namespace App\Domain\Devices\Observers;

use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceImageService;

/**
 * Removes a device's uploaded barrier photo from storage when the device row is deleted (soft or force).
 * Only files WE stored (…/storage/devices/…) are removed; external `image_url`s are left untouched.
 */
class DeviceImageObserver
{
    public function __construct(private readonly DeviceImageService $images) {}

    public function deleting(Device $device): void
    {
        $this->images->purgeFile($device->image_url);
    }
}
