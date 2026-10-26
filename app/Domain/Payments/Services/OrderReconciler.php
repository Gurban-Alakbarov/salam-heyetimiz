<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Support\PaymentSettings;
use App\Support\Time\Clock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hourly reconciliation (R-PAY-13; Tech Spec §14.4): resolve `authorising` orders that have gone
 * quiet via getOrderStatus, then expire in-flight orders past their TTL.
 *
 * K2 — no captured payment is ever lost to the TTL: an order past its TTL is asked at the bank BEFORE it is
 * expired, and orders expired within the late-capture window are re-asked every run (a capture then pays them).
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
     * @return array{rechecked:int, resolved:int, expired:int, late_captured:int}
     */
    public function reconcile(): array
    {
        $cutoff = $this->clock->now()->subMinutes($this->paymentSettings->authorisingRecheckMinutes());
        $rechecked = 0;
        $resolved = 0;

        $this->verifyEach(
            Order::query()
                ->where('status', OrderStatus::Authorising->value)
                ->whereNotNull('bank_order_id')
                ->where('updated_at', '<', $cutoff),
            function (Order $order) use (&$rechecked, &$resolved): void {
                $rechecked++;
                if ($this->verifier->verifyAndApply($order)) {
                    $resolved++;
                }
            },
        );

        // K2 — past the TTL: ask the bank once more before expiring, so a capture is applied, not expired.
        $this->verifyEach(
            Order::query()
                ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Authorising->value])
                ->whereNotNull('bank_order_id')
                ->where('expires_at', '<', $this->clock->now()),
            function (Order $order) use (&$resolved): void {
                if ($this->verifier->verifyAndApply($order)) {
                    $resolved++;
                }
            },
        );

        $expired = $this->orders->expireStale();

        // K2 — expired within the window: a capture that happened after we expired the order pays it late.
        $lateCaptured = 0;
        $windowStart = $this->clock->now()->subHours((int) config('domain.payments.late_capture_window_hours', 72));
        $this->verifyEach(
            Order::query()
                ->where('status', OrderStatus::Expired->value)
                ->whereNotNull('bank_order_id')
                ->where('failed_at', '>=', $windowStart),
            function (Order $order) use (&$lateCaptured): void {
                $this->verifier->verifyAndApply($order);
                if ($order->refresh()->status === OrderStatus::Paid) {
                    $lateCaptured++;
                }
            },
        );

        return ['rechecked' => $rechecked, 'resolved' => $resolved, 'expired' => $expired, 'late_captured' => $lateCaptured];
    }

    /** @param  callable(Order): void  $verify */
    private function verifyEach(Builder $query, callable $verify): void
    {
        $query->orderBy('id')->chunkById(200, function ($orders) use ($verify): void {
            foreach ($orders as $order) {
                try {
                    $verify($order);
                } catch (PaymentProviderUnavailableException) {
                    // Bank unavailable now — leave the order; the next run retries.
                }
            }
        });
    }
}
