<?php

namespace App\Domain\DeviceComm\Listeners;

use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Services\WhitelistService;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Events\RosterUserAdded;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Users\Models\User;

/**
 * When a resident is added to a device roster, enqueue their phone into the device whitelist outbox
 * (R-GSM-07). The actual device programming is the WhitelistSyncJob drain. Mirrors the owner-on-assign
 * path so every authorised resident — owner or member — ends up in the outbox.
 */
class AddUserToWhitelistOnRosterUserAdded
{
    public function __construct(private readonly WhitelistService $whitelist) {}

    public function handle(RosterUserAdded $event): void
    {
        $phone = User::query()->whereKey($event->userId)->value('phone');
        if ($phone === null) {
            return;
        }

        $deviceUserId = DeviceUser::query()
            ->where('device_id', $event->device->getKey())
            ->where('user_id', $event->userId)
            ->where('status', DeviceUserStatus::Active->value)
            ->value('id');

        $this->whitelist->enqueue(
            $event->device,
            WhitelistAction::Add,
            (string) $phone,
            WhitelistService::PRIORITY_ROUTINE,
            $deviceUserId !== null ? (int) $deviceUserId : null,
        );
    }
}
