<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Time\Clock;

/**
 * Complex-mode devices only (IMPLEMENTATION_PLAN B7, BR-13): a resident's phone is on the GSM whitelist
 * exactly while their OWN subscription on that device is active — a roster row alone (an unpaid subscribe
 * intent) never grants call-to-open access. Private devices keep the roster-based whitelist unchanged.
 *
 * Reconciles desired vs. last-enqueued state per (device, phone), so repeated events never stack duplicate
 * Add / Remove changes. Removal of the roster row itself stays with RemoveUserFromWhitelistOnRosterUserRemoved.
 */
final class ComplexWhitelistReconciler
{
    public function __construct(
        private readonly WhitelistService $whitelist,
        private readonly Clock $clock,
    ) {}

    public static function isComplex(DeviceUser $deviceUser): bool
    {
        return $deviceUser->device?->ownership_mode === DeviceOwnershipMode::Complex;
    }

    /** Should this complex roster row be whitelisted right now? */
    public function entitled(DeviceUser $deviceUser): bool
    {
        if ($deviceUser->status !== DeviceUserStatus::Active) {
            return false;
        }

        return Subscription::query()
            ->where('device_user_id', $deviceUser->getKey())
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '>', $this->clock->now())
            ->exists();
    }

    public function reconcile(DeviceUser $deviceUser): void
    {
        $deviceUser->loadMissing(['device', 'user:id,phone']);
        $phone = $deviceUser->user?->phone;
        if ($phone === null || ! self::isComplex($deviceUser)) {
            return;
        }

        $want = $this->entitled($deviceUser);
        $last = WhitelistChange::query()
            ->where('device_id', $deviceUser->device_id)
            ->where(fn ($q) => $q->where('phone', $phone)->orWhere('action', WhitelistAction::Clear->value))
            ->orderByDesc('seq')
            ->value('action');
        $present = $last === WhitelistAction::Add || $last === WhitelistAction::Add->value;

        if ($want === $present) {
            return;
        }

        $this->whitelist->enqueue(
            $deviceUser->device,
            $want ? WhitelistAction::Add : WhitelistAction::Remove,
            (string) $phone,
            WhitelistService::PRIORITY_ROUTINE,
            (int) $deviceUser->getKey(),
        );
    }
}
