<?php

namespace App\Domain\Devices\Queries;

use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use Illuminate\Support\Collection;

/**
 * Read model for a complex's shared devices (IMPLEMENTATION_PLAN B4; exposed by the resident API in B7).
 * Only `complex`-mode, non-deleted, active devices of the complex. For a given caller each device carries
 * `caller_subscription_status`: none | pending_payment | active | expired (their OWN subscription only —
 * never another resident's). Authorization (complex membership) is the caller's job (ComplexPolicy).
 */
final class ComplexDeviceQuery
{
    /** @return Collection<int, Device> */
    public function forComplex(int $complexId, ?int $callerUserId = null): Collection
    {
        $devices = Device::query()
            ->where('complex_id', $complexId)
            ->where('ownership_mode', DeviceOwnershipMode::Complex->value)
            ->where('status', DeviceStatus::Active->value)
            ->orderBy('location_label')
            ->orderBy('id')
            ->get();

        if ($callerUserId === null || $devices->isEmpty()) {
            return $devices;
        }

        $rows = DeviceUser::query()
            ->whereIn('device_id', $devices->modelKeys())
            ->where('user_id', $callerUserId)
            ->where('status', DeviceUserStatus::Active->value)
            ->get()
            ->keyBy('device_id');

        $subscriptions = Subscription::query()
            ->whereIn('device_user_id', $rows->pluck('id'))
            ->get()
            ->keyBy('device_user_id');

        return $devices->each(function (Device $device) use ($rows, $subscriptions): void {
            $row = $rows->get($device->getKey());
            $sub = $row !== null ? $subscriptions->get($row->getKey()) : null;
            $device->caller_subscription_status = $this->statusOf($sub);
        });
    }

    private function statusOf(?Subscription $subscription): string
    {
        if ($subscription === null) {
            return 'none';
        }

        return match (true) {
            $subscription->status === SubscriptionStatus::Active && $subscription->ends_at?->isFuture() => 'active',
            $subscription->status === SubscriptionStatus::PendingPayment => 'pending_payment',
            default => 'expired',
        };
    }
}
