<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Payments\Models\Order;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\RenewalService;
use App\Domain\Users\Models\User;

final class RenewSubscription
{
    public function __construct(private readonly RenewalService $renewals) {}

    public function handle(Subscription $subscription, User $payer, ?string $returnUrl, ?string $idempotencyKey): Order
    {
        return $this->renewals->initiateRenewal($subscription, $payer, $returnUrl, $idempotencyKey);
    }
}
