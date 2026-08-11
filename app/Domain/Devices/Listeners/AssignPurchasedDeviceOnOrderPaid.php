<?php

namespace App\Domain\Devices\Listeners;

use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceOwnershipService;
use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;

/**
 * The device-sale activation trigger (R-DOM-13): when an order with a `device` item is paid, bind the
 * payer as the device's owner. Mirrors the Subscriptions activation listener — Payments fires the
 * event, this module reacts (R-ARCH-06/07). Idempotent: an already-assigned device is skipped.
 */
class AssignPurchasedDeviceOnOrderPaid
{
    public function __construct(private readonly DeviceOwnershipService $ownership) {}

    public function handle(OrderPaid $event): void
    {
        $items = $event->order->items()->where('item_type', 'device')->get();

        if ($items->isEmpty()) {
            return;
        }

        /** @var User|null $payer */
        $payer = User::query()->find($event->order->payer_user_id);
        if ($payer === null) {
            return;
        }

        foreach ($items as $item) {
            if ($item->referenced_id === null) {
                continue;
            }

            /** @var Device|null $device */
            $device = Device::query()->find($item->referenced_id);
            if ($device === null || $device->owner_user_id !== null) {
                continue; // unknown or already assigned (idempotent)
            }

            $this->ownership->assignOwner($device, $payer, [], ActorKind::System, null, 'purchase');
        }
    }
}
