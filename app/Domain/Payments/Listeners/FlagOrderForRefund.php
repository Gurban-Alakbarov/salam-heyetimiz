<?php

namespace App\Domain\Payments\Listeners;

use App\Domain\Payments\Services\OrderService;
use App\Domain\Subscriptions\Events\PaidSubscriptionUndeliverable;

/**
 * K3 — Subscriptions could not deliver a paid line (access gone). Payments owns the money: the order is
 * flagged `refund_required` for finance to refund by hand (no automatic refunds — decision 2026-10-26).
 */
class FlagOrderForRefund
{
    public function __construct(private readonly OrderService $orders) {}

    public function handle(PaidSubscriptionUndeliverable $event): void
    {
        $this->orders->flagRefundRequired(
            $event->order,
            (int) $event->subscription->id,
            $event->amountMinor,
            $event->reason,
        );
    }
}
