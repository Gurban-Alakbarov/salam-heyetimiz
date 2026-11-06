<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Payments\Events\OrderPartiallyRefunded;
use App\Domain\Payments\Events\OrderRefunded;
use App\Domain\Payments\Events\PaymentCallbackReceived;
use App\Domain\Payments\Jobs\SendReceiptEmailJob;
use App\Domain\Payments\Listeners\FlagOrderForRefund;
use App\Domain\Payments\Listeners\HandlePaymentCallbackReceived;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Policies\OrderPolicy;
use App\Domain\Payments\Policies\RefundPolicy;
use App\Domain\Subscriptions\Events\PaidSubscriptionUndeliverable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Payments (Backend Arch §14.8): orders, Kapital integration, hardened callback (CRIT-07),
 * refunds with pro-rata (HIGH-08), allowlist-encrypted payment_logs (HIGH-15), reconciler.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);

        Event::listen(PaymentCallbackReceived::class, HandlePaymentCallbackReceived::class);
        Event::listen(PaidSubscriptionUndeliverable::class, FlagOrderForRefund::class); // K3

        Event::listen(OrderPaid::class, static fn (OrderPaid $e) => SendReceiptEmailJob::dispatch((int) $e->order->id));
        Event::listen(OrderRefunded::class, static fn (OrderRefunded $e) => SendReceiptEmailJob::dispatch((int) $e->order->id, (int) $e->refund->id));
        Event::listen(OrderPartiallyRefunded::class, static fn (OrderPartiallyRefunded $e) => SendReceiptEmailJob::dispatch((int) $e->order->id, (int) $e->refund->id));
    }
}
