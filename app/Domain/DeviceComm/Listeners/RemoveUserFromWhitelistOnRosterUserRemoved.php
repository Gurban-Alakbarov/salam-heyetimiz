<?php

namespace App\Domain\DeviceComm\Listeners;

use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Services\WhitelistService;
use App\Domain\Roster\Events\RosterUserRemoved;
use App\Domain\Users\Models\User;

/**
 * When a resident is removed from a device roster, enqueue a whitelist removal of their phone into the
 * device outbox (R-GSM-07). The WhitelistSyncJob drain applies it to the device.
 */
class RemoveUserFromWhitelistOnRosterUserRemoved
{
    public function __construct(private readonly WhitelistService $whitelist) {}

    public function handle(RosterUserRemoved $event): void
    {
        $phone = User::query()->whereKey($event->userId)->value('phone');
        if ($phone === null) {
            return;
        }

        $this->whitelist->enqueue(
            $event->device,
            WhitelistAction::Remove,
            (string) $phone,
            WhitelistService::PRIORITY_ROUTINE,
        );
    }
}
