<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionPeriodKind;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Events\SubscriptionActivated;
use App\Domain\Subscriptions\Events\SubscriptionCancelled;
use App\Domain\Subscriptions\Events\SubscriptionExpired;
use App\Domain\Subscriptions\Events\SubscriptionRefunded;
use App\Domain\Subscriptions\Events\SubscriptionRenewed;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Support\RefundProration;
use App\Domain\Subscriptions\Support\SubscriptionTerm;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Owns the subscription aggregate lifecycle (BACKEND §14.7; Tech Spec §13). Transactions open here
 * (R-ARCH-05). Money is never touched — that is the Payments module; this module owns entitlement
 * time and status, and emits the events DeviceComm/Notifications react to (R-ARCH-06/07).
 */
final class SubscriptionService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly SubscriptionTerm $term,
        private readonly RefundProration $proration,
    ) {}

    /** Create (or return) the single pending subscription for a device_user, with snapshot pricing. */
    public function createPending(DeviceUser $deviceUser, SubscriptionTier $tier): Subscription
    {
        $existing = Subscription::query()->where('device_user_id', $deviceUser->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $now = $this->clock->now();
        $prices = (array) config('domain.subscriptions.default_prices_minor', []);

        return Subscription::query()->create([
            'device_user_id' => $deviceUser->getKey(),
            'tier' => $tier,
            'price_minor' => (int) ($prices[$tier->priceKey()] ?? 0),
            'currency' => 'AZN',
            'term_days' => (int) config('domain.subscriptions.term_days', 365),
            'starts_at' => $now,
            'ends_at' => $now,
            'status' => SubscriptionStatus::PendingPayment,
            'auto_renew' => false,
        ]);
    }

    /**
     * Admin comp grant (NO payment): activate — or extend — the entitlement without an order.
     *
     * `subscription_periods.order_id` is NOT NULL (a period always belongs to a paid order), so a comp
     * grant deliberately records no billing period; the grant itself is captured in `audit_logs` via
     * SubscriptionGranted. Granting on a still-live subscription EXTENDS it from its current `ends_at`
     * (no lost time); on a pending/expired/cancelled one it starts a fresh term from now. Emits
     * SubscriptionActivated so DeviceComm whitelists the user exactly as a paid activation would.
     */
    public function grantManual(DeviceUser $deviceUser, SubscriptionTier $tier, int $termDays): Subscription
    {
        $now = $this->clock->now();

        return DB::transaction(function () use ($deviceUser, $tier, $termDays, $now): Subscription {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->where('device_user_id', $deviceUser->getKey())->first();

            if ($subscription === null) {
                /** @var Subscription $subscription */
                $subscription = Subscription::query()->create([
                    'device_user_id' => $deviceUser->getKey(),
                    'tier' => $tier,
                    'price_minor' => 0, // comp — no money changed hands
                    'currency' => 'AZN',
                    'term_days' => $termDays,
                    'starts_at' => $now,
                    'ends_at' => $now->addDays($termDays),
                    'status' => SubscriptionStatus::Active,
                    'auto_renew' => false,
                ]);
            } else {
                $live = $subscription->status === SubscriptionStatus::Active
                    && $subscription->ends_at !== null
                    && $subscription->ends_at->greaterThan($now);

                $start = $live ? $subscription->ends_at->toImmutable() : $now;

                $subscription->forceFill([
                    'tier' => $tier->value,
                    'term_days' => $termDays,
                    'status' => SubscriptionStatus::Active->value,
                    'starts_at' => $live ? $subscription->starts_at : $now,
                    'ends_at' => $start->addDays($termDays),
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ])->save();
            }

            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }

    /** Apply a paid order to the subscription: first payment activates, subsequent payments renew. */
    public function applyFromPaidOrder(Subscription $subscription, Order $order, int $amountMinor): Subscription
    {
        return $subscription->status === SubscriptionStatus::PendingPayment
            ? $this->activate($subscription, $order, $amountMinor)
            : $this->renew($subscription, $order, $amountMinor);
    }

    public function activate(Subscription $subscription, Order $order, int $amountMinor): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Active) {
            return $subscription; // idempotent
        }

        $period = $this->term->initial($subscription->term_days, $this->clock->now());

        return DB::transaction(function () use ($subscription, $order, $amountMinor, $period): Subscription {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active->value,
                'starts_at' => $period['start'],
                'ends_at' => $period['end'],
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ])->save();

            $subscription->periods()->create([
                'order_id' => $order->id,
                'kind' => SubscriptionPeriodKind::Initial,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'amount_minor' => $amountMinor,
            ]);

            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }

    public function renew(Subscription $subscription, Order $order, int $amountMinor): Subscription
    {
        $graceDays = (int) config('domain.subscriptions.grace_days', 7);
        $period = $this->term->renew($subscription->ends_at->toImmutable(), $subscription->term_days, $graceDays, $this->clock->now());

        return DB::transaction(function () use ($subscription, $order, $amountMinor, $period): Subscription {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active->value,
                'ends_at' => $period['end'],
            ])->save();

            $subscription->periods()->create([
                'order_id' => $order->id,
                'kind' => SubscriptionPeriodKind::Renewal,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'amount_minor' => $amountMinor,
            ]);

            SubscriptionRenewed::dispatch($subscription);

            return $subscription;
        });
    }

    public function expire(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return $subscription;
        }

        $subscription->forceFill(['status' => SubscriptionStatus::Expired->value])->save();
        SubscriptionExpired::dispatch($subscription);

        return $subscription;
    }

    public function cancel(Subscription $subscription, ?string $reason = null): Subscription
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Cancelled->value,
            'cancelled_at' => $this->clock->now(),
            'cancellation_reason' => $reason,
        ])->save();

        SubscriptionCancelled::dispatch($subscription, $reason);

        return $subscription;
    }

    /**
     * Apply a refund's subscription impact (§14.5.1). $moneyFull reflects the Payments outcome
     * (OrderRefunded vs OrderPartiallyRefunded); a partial money refund still becomes a full revoke
     * if the pro-rata removal zeroes the remaining term.
     */
    public function applyRefund(Subscription $subscription, int $refundAmountMinor, Order $order, bool $moneyFull): Subscription
    {
        $now = $this->clock->now();

        if ($moneyFull) {
            $full = true;
            $newEndsAt = $now;
        } else {
            $result = $this->proration->calculate(
                $subscription->price_minor,
                $subscription->term_days,
                $refundAmountMinor,
                $subscription->ends_at->toImmutable(),
                $now,
            );
            $full = $result->fullRefund;
            $newEndsAt = $result->newEndsAt;
        }

        $periodStart = $subscription->starts_at?->toImmutable() ?? $now;

        return DB::transaction(function () use ($subscription, $order, $refundAmountMinor, $full, $newEndsAt, $periodStart): Subscription {
            $subscription->periods()->create([
                'order_id' => $order->id,
                'kind' => SubscriptionPeriodKind::Refund,
                'period_start' => $periodStart,
                'period_end' => $newEndsAt,
                'amount_minor' => -1 * $refundAmountMinor,
            ]);

            $subscription->forceFill([
                'status' => ($full ? SubscriptionStatus::Refunded : SubscriptionStatus::Active)->value,
                'ends_at' => $newEndsAt,
            ])->save();

            SubscriptionRefunded::dispatch($subscription, $refundAmountMinor, $full);

            return $subscription;
        });
    }
}
