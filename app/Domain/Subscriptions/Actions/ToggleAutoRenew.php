<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\AutoRenewService;

final class ToggleAutoRenew
{
    public function __construct(private readonly AutoRenewService $autoRenew) {}

    public function handle(Subscription $subscription, bool $enable, ?int $cardTokenId): Subscription
    {
        return $this->autoRenew->toggle($subscription, $enable, $cardTokenId);
    }
}
