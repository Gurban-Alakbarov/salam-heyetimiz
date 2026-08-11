<?php

namespace App\Domain\Subscriptions\Queries;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Services\DeviceLookup;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\DTOs\DeviceAccessResult;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SuspensionReason;
use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Time\Clock;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Device-access eligibility + per-caller suspension reason (Tech Spec §13.9 / CRIT-02; R-DOM-05/07).
 * "Active" is evaluated in real time (status=active AND ends_at in the future) on the primary DB —
 * never cached (HIGH-06) — so a lapsed-but-not-yet-swept subscription cannot open. This is the read
 * the open-command pipeline (DeviceComm, batch 07) consults.
 */
final class SubscriptionStatusQuery
{
    public function __construct(
        private readonly Clock $clock,
        private readonly DeviceLookup $devices,
    ) {}

    public function for(int $userId, int $deviceId): DeviceAccessResult
    {
        $deviceStatus = $this->devices->status($deviceId);

        if ($deviceStatus === null) {
            return new DeviceAccessResult(false, SuspensionReason::None);
        }

        if ($deviceStatus === DeviceStatus::Disabled) {
            return new DeviceAccessResult(false, SuspensionReason::DeviceDisabled);
        }

        $callerDeviceUser = DeviceUser::query()
            ->where('device_id', $deviceId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($callerDeviceUser === null) {
            // No active roster relationship → no access (and nothing to "suspend").
            return new DeviceAccessResult(false, SuspensionReason::None);
        }

        if ($this->hasActiveSubscription($callerDeviceUser->getKey())) {
            return new DeviceAccessResult(true, SuspensionReason::None);
        }

        $othersActive = $this->anyOtherActiveSubscription($deviceId, (int) $callerDeviceUser->getKey());
        $isOwner = $callerDeviceUser->role === DeviceUserRole::Owner;

        if ($isOwner && $othersActive) {
            return new DeviceAccessResult(false, SuspensionReason::OwnerSubExpiredOthersActive);
        }

        if (! $othersActive) {
            return new DeviceAccessResult(false, SuspensionReason::DeviceSuspended);
        }

        return new DeviceAccessResult(false, SuspensionReason::SubscriptionExpired);
    }

    private function hasActiveSubscription(int $deviceUserId): bool
    {
        return Subscription::query()
            ->where('device_user_id', $deviceUserId)
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '>', $this->clock->now())
            ->exists();
    }

    private function anyOtherActiveSubscription(int $deviceId, int $excludeDeviceUserId): bool
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '>', $this->clock->now())
            ->whereHas('deviceUser', fn (Builder $q) => $q
                ->where('device_id', $deviceId)
                ->where('status', 'active')
                ->whereKeyNot($excludeDeviceUserId))
            ->exists();
    }
}
