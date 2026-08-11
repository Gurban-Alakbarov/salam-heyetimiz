<?php

namespace App\Domain\Subscriptions;

use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Payments\Events\OrderPartiallyRefunded;
use App\Domain\Payments\Events\OrderRefunded;
use App\Domain\Subscriptions\Listeners\ActivateSubscriptionOnOrderPaid;
use App\Domain\Subscriptions\Listeners\AdjustSubscriptionOnOrderPartiallyRefunded;
use App\Domain\Subscriptions\Listeners\AdjustSubscriptionOnOrderRefunded;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Policies\SubscriptionPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Subscriptions (Backend Arch §14.7): per-(user, device) entitlement lifecycle,
 * subscription_periods history, expiry sweep, reminders, §13.9 edge cases (CRIT-02).
 *
 * Consumes the Payments order events (R-ARCH-07): OrderPaid drives the activation /
 * renewal trigger; the refund events drive §14.5.1 pro-rata adjustment.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        Event::listen(OrderPaid::class, ActivateSubscriptionOnOrderPaid::class);
        Event::listen(OrderRefunded::class, AdjustSubscriptionOnOrderRefunded::class);
        Event::listen(OrderPartiallyRefunded::class, AdjustSubscriptionOnOrderPartiallyRefunded::class);
    }
}
