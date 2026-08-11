<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentCallbackOutcome;
use App\Domain\Payments\Models\PaymentCallback;
use App\Domain\Payments\Services\PaymentCallbackService;
use App\Domain\Payments\Services\PaymentVerifierService;
use Throwable;

/**
 * Applies a received callback by consulting the bank's authoritative status (never the callback
 * body — R-PAY-04), then records the outcome on the callback ledger.
 */
final class ProcessPaymentCallback
{
    public function __construct(
        private readonly PaymentVerifierService $verifier,
        private readonly PaymentCallbackService $callbacks,
    ) {}

    public function handle(PaymentCallback $callback): void
    {
        try {
            $this->verifier->verifyAndApplyByBankOrderId($callback->bank_order_id);
            $outcome = $callback->order_id === null
                ? PaymentCallbackOutcome::Unmatched
                : PaymentCallbackOutcome::Applied;
            $this->callbacks->markProcessed($callback, $outcome);
        } catch (Throwable $e) {
            $this->callbacks->markProcessed($callback, PaymentCallbackOutcome::Error);

            throw $e; // let the queue retry transient bank failures
        }
    }
}
