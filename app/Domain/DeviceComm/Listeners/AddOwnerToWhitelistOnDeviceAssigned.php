<?php

namespace App\Domain\DeviceComm\Listeners;

use App\Domain\Devices\Events\DeviceAssigned;
use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Services\WhitelistService;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Users\Models\User;

/**
 * When a device is assigned an owner (purchase or technical), enqueue the owner's phone into the
 * device whitelist outbox (R-GSM-07). The actual device programming is the WhitelistSyncJob drain
 * in the GSM batch.
 */
class AddOwnerToWhitelistOnDeviceAssigned
{
    public function __construct(private readonly WhitelistService $whitelist) {}

    public function handle(DeviceAssigned $event): void
    {
        $phone = User::query()->whereKey($event->ownerUserId)->value('phone');
        if ($phone === null) {
            return;
        }

        $deviceUserId = DeviceUser::query()
            ->where('device_id', $event->device->getKey())
            ->where('user_id', $event->ownerUserId)
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
