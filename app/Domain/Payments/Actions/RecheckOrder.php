<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\PaymentVerifierService;

final class RecheckOrder
{
    public function __construct(private readonly PaymentVerifierService $verifier) {}

    /** Re-query the bank and apply the authoritative state; returns the refreshed order. */
    public function handle(Order $order): Order
    {
        $this->verifier->verifyAndApply($order);

        return $order->refresh();
    }
}
