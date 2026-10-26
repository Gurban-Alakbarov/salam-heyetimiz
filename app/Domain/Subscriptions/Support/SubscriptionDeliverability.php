<?php

namespace App\Domain\Subscriptions\Support;

use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Models\Subscription;

/**
 * K3 — can a subscription still be SERVED? Access lives on the roster row: once it is revoked (Komendant /
 * family head removal, resident deleted, abandoned intent, admin removal) or the device is gone, a payment
 * for it buys nothing. Such a subscription is never (re)activated by a payment and never offered for one.
 */
final class SubscriptionDeliverability
{
    public const ACCESS_REMOVED = 'access_removed';

    public const DEVICE_REMOVED = 'device_removed';

    /** Null while the subscription can be served; otherwise why it cannot (ACCESS_REMOVED / DEVICE_REMOVED). */
    public function blocker(Subscription $subscription): ?string
    {
        /** @var DeviceUser|null $row */
        $row = DeviceUser::query()->find($subscription->device_user_id);
        if ($row === null || $row->status !== DeviceUserStatus::Active) {
            return self::ACCESS_REMOVED;
        }

        // Decommissioned devices are soft-deleted, so the default scope already hides them.
        return Device::query()->whereKey($row->device_id)->exists() ? null : self::DEVICE_REMOVED;
    }
}
