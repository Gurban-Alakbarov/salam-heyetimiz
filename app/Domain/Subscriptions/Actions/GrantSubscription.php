<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Exceptions\RosterMemberNotFoundException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Events\SubscriptionGranted;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;

/**
 * Admin "activate subscription without payment" (adminGrantSubscription). Entitlement time is granted
 * to an EXISTING active roster membership — this never creates the membership itself (use the roster
 * add-user flow for that) and never touches money (Payments owns orders/refunds).
 */
final class GrantSubscription
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Device $device, int $userId, SubscriptionTier $tier, int $termDays, ?AdminUser $admin): Subscription
    {
        /** @var DeviceUser|null $deviceUser */
        $deviceUser = DeviceUser::query()
            ->where('device_id', $device->getKey())
            ->where('user_id', $userId)
            ->where('status', DeviceUserStatus::Active->value)
            ->first();

        if ($deviceUser === null) {
            throw new RosterMemberNotFoundException();
        }

        $subscription = $this->subscriptions->grantManual($deviceUser, $tier, $termDays);

        SubscriptionGranted::dispatch(
            $subscription,
            (int) $device->getKey(),
            $userId,
            $admin !== null ? (int) $admin->getKey() : null,
        );

        return $subscription;
    }
}
