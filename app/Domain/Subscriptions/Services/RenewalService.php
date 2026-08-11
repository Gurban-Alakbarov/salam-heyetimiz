<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Payments\Actions\CreateOrder;
use App\Domain\Payments\DTOs\OrderCreationData;
use App\Domain\Payments\DTOs\OrderItemData;
use App\Domain\Payments\Enums\OrderPurpose;
use App\Domain\Payments\Models\Order;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Exceptions\SubscriptionNotEligibleForRenewalException;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;

/**
 * Manual renewal (Tech Spec §13; openapi renewSubscription). Builds a tier-priced sub_renewal order
 * and delegates to the Payments module (R-ARCH-06). The order item type mirrors the subscription
 * tier so Payments' server-side pricing charges the correct fee (renewal pricing logic). On
 * OrderPaid the renewal is applied by this module's listener.
 */
final class RenewalService
{
    public function __construct(private readonly CreateOrder $createOrder) {}

    public function initiateRenewal(Subscription $subscription, User $payer, ?string $returnUrl, ?string $idempotencyKey): Order
    {
        $this->assertRenewable($subscription);

        $data = new OrderCreationData(
            purpose: OrderPurpose::SubRenewal,
            items: [new OrderItemData(
                itemType: $subscription->tier->renewalItemType(),
                referencedId: (int) $subscription->id,
                quantity: 1,
            )],
            autoRenew: false,
            returnUrl: $returnUrl,
        );

        return $this->createOrder->handle($payer, $data, $idempotencyKey);
    }

    private function assertRenewable(Subscription $subscription): void
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Expired], true)) {
            throw new SubscriptionNotEligibleForRenewalException(
                'Subscription cannot be renewed in its current state.',
                ['status' => $subscription->status->value],
            );
        }
    }
}
