<?php

namespace App\Domain\DeviceComm\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Events\WhitelistResyncRequested;
use App\Domain\DeviceComm\Services\WhitelistService;

/** Force a full whitelist resync (adminResyncWhitelist) — enqueue a clear + add-all at priority 10. */
final class ResyncWhitelist
{
    public function __construct(private readonly WhitelistService $whitelist) {}

    public function handle(Device $device, AdminUser $admin): int
    {
        $count = $this->whitelist->enqueueResync($device, $admin);

        WhitelistResyncRequested::dispatch((int) $device->getKey(), (int) $admin->getKey(), $count);

        return $count;
    }
}
