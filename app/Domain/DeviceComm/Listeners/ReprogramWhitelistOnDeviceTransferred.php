<?php

namespace App\Domain\DeviceComm\Listeners;

use App\Domain\Devices\Events\DeviceTransferred;
use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Services\WhitelistService;
use App\Domain\Users\Models\User;

/**
 * On ownership transfer, enqueue removal of the previous owner and addition of the new owner to the
 * device whitelist (R-GSM-07). Existing members the transfer kept stay programmed; ones it revoked
 * are handled by their own roster revocation flow (Roster batch).
 */
class ReprogramWhitelistOnDeviceTransferred
{
    public function __construct(private readonly WhitelistService $whitelist) {}

    public function handle(DeviceTransferred $event): void
    {
        if ($event->previousOwnerUserId !== null) {
            $previousPhone = User::query()->whereKey($event->previousOwnerUserId)->value('phone');
            if ($previousPhone !== null) {
                $this->whitelist->enqueue($event->device, WhitelistAction::Remove, (string) $previousPhone);
            }
        }

        $newPhone = User::query()->whereKey($event->newOwnerUserId)->value('phone');
        if ($newPhone !== null) {
            $this->whitelist->enqueue($event->device, WhitelistAction::Add, (string) $newPhone);
        }
    }
}
