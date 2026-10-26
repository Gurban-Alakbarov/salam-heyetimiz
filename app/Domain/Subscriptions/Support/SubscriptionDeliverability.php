<?php

namespace App\Domain\Subscriptions\Support;

use App\Domain\Admin\Models\Complex;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
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

    /** A7 — the device belongs to a deactivated complex: no new payment / renewal for it. */
    public const COMPLEX_INACTIVE = 'complex_inactive';

    /** Null while the subscription can be served; otherwise why it cannot (ACCESS_REMOVED / DEVICE_REMOVED). */
    public function blocker(Subscription $subscription): ?string
    {
        /** @var DeviceUser|null $row */
        $row = DeviceUser::query()->find($subscription->device_user_id);
        if ($row === null || $row->status !== DeviceUserStatus::Active) {
            return self::ACCESS_REMOVED;
        }

        // Decommissioned devices are soft-deleted, so the default scope already hides them.
        $device = Device::query()->find($row->device_id);
        if ($device === null) {
            return self::DEVICE_REMOVED;
        }

        if ($device->ownership_mode === DeviceOwnershipMode::Complex && $device->complex_id !== null
            && Complex::query()->whereKey($device->complex_id)->where('is_active', false)->exists()) {
            return self::COMPLEX_INACTIVE;
        }

        return null;
    }
}
