<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Support\PaymentSettings;
use App\Support\Time\Clock;

/**
 * Hourly reconciliation (R-PAY-13; Tech Spec §14.4): resolve `authorising` orders that have gone
 * quiet via getOrderStatus, then expire in-flight orders past their TTL.
 */
final class OrderReconciler
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PaymentVerifierService $verifier,
        private readonly OrderService $orders,
        private readonly PaymentSettings $paymentSettings,
    ) {}

    /**
     * @return array{rechecked:int, resolved:int, expired:int}
     */
    public function reconcile(): array
    {
        $cutoff = $this->clock->now()->subMinutes($this->paymentSettings->authorisingRecheckMinutes());
        $rechecked = 0;
        $resolved = 0;

        Order::query()
            ->where('status', OrderStatus::Authorising->value)
            ->whereNotNull('bank_order_id')
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$rechecked, &$resolved): void {
                foreach ($orders as $order) {
                    try {
                        $rechecked++;
                        if ($this->verifier->verifyAndApply($order)) {
                            $resolved++;
                        }
                    } catch (PaymentProviderUnavailableException) {
                        // Bank unavailable now — leave the order; the next run retries.
                    }
                }
            });

        $expired = $this->orders->expireStale();

        return ['rechecked' => $rechecked, 'resolved' => $resolved, 'expired' => $expired];
    }
}
