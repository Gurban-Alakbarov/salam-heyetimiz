<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\DTOs\OrderCreationData;
use App\Domain\Payments\DTOs\OrderItemData;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Payments\Enums\OrderPurpose;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\OrderService;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Exceptions\SubscriptionAlreadyActiveException;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A complex resident subscribes to one shared device (IMPLEMENTATION_PLAN §13 / B7). Under a device row lock:
 * the resident's own roster row (role=user; reactivated if revoked) → their own pending subscription
 * (tier=main, 12 AZN / 30 days; a cancelled/expired row is reopened — B2) → an order paid by themself.
 * The roster row alone grants nothing: open + whitelist need the paid subscription (B4 / B7). Unpaid intents
 * are swept (SweepAbandonedSubscriptionIntents). Idempotent through the order Idempotency-Key and the
 * single in-flight order per subscription.
 */
final class StartDeviceSubscription
{
    public function __construct(
        private readonly RosterService $roster,
        private readonly SubscriptionService $subscriptions,
        private readonly OrderService $orders,
        private readonly Clock $clock,
    ) {}

    public function handle(User $user, int $complexId, int $deviceId, string $idempotencyKey, ?string $returnUrl = null): Order
    {
        $device = Device::query()
            ->whereKey($deviceId)
            ->where('complex_id', $complexId)
            ->where('ownership_mode', DeviceOwnershipMode::Complex->value)
            ->first() ?? throw (new ModelNotFoundException)->setModel(Device::class, [$deviceId]);

        // membership + active device — DevicePolicy::subscribe (B4), server-side.
        Gate::forUser($user)->authorize('subscribe', $device);

        $subscription = DB::transaction(function () use ($device, $user): Subscription {
            Device::query()->whereKey($device->getKey())->lockForUpdate()->first();

            $row = DeviceUser::query()
                ->where('device_id', $device->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', DeviceUserStatus::Active->value)
                ->first()
                ?? $this->roster->addMember($device, $user, DeviceUserRole::User, ActorKind::User, (int) $user->getKey());

            $existing = Subscription::query()->where('device_user_id', $row->getKey())->first();
            if ($existing !== null && $existing->status === SubscriptionStatus::Active && $existing->ends_at->greaterThan($this->clock->now())) {
                throw new SubscriptionAlreadyActiveException((int) $existing->getKey());
            }

            return $this->subscriptions->createPending($row, SubscriptionTier::Main);
        });

        return $this->orders->create($user, new OrderCreationData(
            purpose: OrderPurpose::SubMain,
            items: [new OrderItemData(OrderItemType::SubMain, (int) $subscription->getKey(), 1)],
            returnUrl: $returnUrl,
        ), $idempotencyKey);
    }
}
