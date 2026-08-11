<?php

namespace App\Domain\Payments\Listeners;

use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Events\PaymentCallbackReceived;
use App\Domain\Payments\Jobs\ProcessPaymentCallbackJob;
use App\Domain\Payments\Jobs\RecheckOrderStatusJob;
use Illuminate\Support\Carbon;

/**
 * Routes a received callback: PENDING → schedule a backed-off recheck (never mutate the order from
 * a PENDING body — R-PAY-05); terminal + matched → enqueue verify-and-apply. Unmatched callbacks
 * are left for reconciliation.
 */
class HandlePaymentCallbackReceived
{
    public function handle(PaymentCallbackReceived $event): void
    {
        $callback = $event->callback;

        if ($callback->order_id === null) {
            return;
        }

        if (BankStatus::tryFromString($callback->bank_status) === BankStatus::Pending) {
            RecheckOrderStatusJob::dispatch($callback->order_id)->delay(Carbon::now()->addSeconds(30));

            return;
        }

        ProcessPaymentCallbackJob::dispatch($callback->id);
    }
}
