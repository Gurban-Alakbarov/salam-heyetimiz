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
use App\Domain\Subscriptions\Support\SubscriptionPriceResolver;
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
        private readonly SubscriptionPriceResolver $prices,
    ) {}

    /**
     * Create (or return) the single subscription row for a device_user, ready for payment, with a commercial
     * price/term snapshot (12 AZN / 30 days — B2). device_user_id is UNIQUE, so a member who left and came
     * back reuses their row: a cancelled/expired/refunded one is reopened for payment (history kept);
     * a pending or active one is returned untouched (never re-priced).
     */
    public function createPending(DeviceUser $deviceUser, SubscriptionTier $tier): Subscription
    {
        /** @var Subscription|null $existing */
        $existing = Subscription::query()->where('device_user_id', $deviceUser->getKey())->first();
        if ($existing !== null) {
            return $this->isReopenable($existing) ? $this->reopenForPayment($existing, $tier) : $existing;
        }

        $now = $this->clock->now();
        $terms = $this->prices->commercial($tier);

        return Subscription::query()->create([
            'device_user_id' => $deviceUser->getKey(),
            'tier' => $tier,
            'price_minor' => $terms['price_minor'],
            'currency' => $terms['currency'],
            'term_days' => $terms['term_days'],
            'starts_at' => $now,
            'ends_at' => $now,
            'status' => SubscriptionStatus::PendingPayment,
            'auto_renew' => false,
        ]);
    }

    /**
     * ReopenSubscriptionForPayment (B2): a cancelled / expired / refunded row goes back to pending_payment
     * with a fresh commercial snapshot. subscription_periods (the paid history) are never touched.
     */
    public function reopenForPayment(Subscription $subscription, SubscriptionTier $tier): Subscription
    {
        if (! $this->isReopenable($subscription)) {
            return $subscription;
        }

        $now = $this->clock->now();
        $terms = $this->prices->commercial($tier);

        $subscription->forceFill([
            'tier' => $tier->value,
            'price_minor' => $terms['price_minor'],
            'currency' => $terms['currency'],
            'term_days' => $terms['term_days'],
            'status' => SubscriptionStatus::PendingPayment->value,
            'starts_at' => $now,
            'ends_at' => $now,
            'auto_renew' => false,
            'last_reminder_kind' => null,
            'last_reminder_sent_at' => null,
        ])->save();

        return $subscription;
    }

    private function isReopenable(Subscription $subscription): bool
    {
        return in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired, SubscriptionStatus::Refunded], true);
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
    /**
     * A3 — a free grant is recorded (comp_granted_at / _by_admin_id). $tier null: a new subscription takes the
     * row's tier — own access (owner, or a resident's own row) is main, a family grant is additional; an existing
     * subscription keeps its tier.
     */
    public function grantManual(DeviceUser $deviceUser, ?SubscriptionTier $tier, int $termDays, ?int $adminId = null): Subscription
    {
        $now = $this->clock->now();

        return DB::transaction(function () use ($deviceUser, $tier, $termDays, $now, $adminId): Subscription {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->where('device_user_id', $deviceUser->getKey())->first();

            if ($subscription === null) {
                /** @var Subscription $subscription */
                $subscription = Subscription::query()->create([
                    'device_user_id' => $deviceUser->getKey(),
                    'tier' => $tier ?? ($deviceUser->family_link_id === null ? SubscriptionTier::Main : SubscriptionTier::Additional),
                    'price_minor' => 0, // comp — no money changed hands
                    'comp_granted_at' => $now,
                    'comp_granted_by_admin_id' => $adminId,
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
                    'tier' => ($tier ?? $subscription->tier)->value,
                    'term_days' => $termDays,
                    'comp_granted_at' => $now,
                    'comp_granted_by_admin_id' => $adminId,
                    'status' => SubscriptionStatus::Active->value,
                    'starts_at' => $live ? $subscription->starts_at : $now,
                    'ends_at' => $start->addDays($termDays),
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                    // Y1 — a new end date starts a new reminder cycle (D-7 / D-1 fire again before it).
                    'last_reminder_kind' => null,
                    'last_reminder_sent_at' => null,
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
                'last_reminder_kind' => null, // Y1 — a new end date, a new reminder cycle
                'last_reminder_sent_at' => null,
            ])->save();

            $subscription->periods()->create([
                'order_id' => $order->id,
                'kind' => SubscriptionPeriodKind::Initial,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'amount_minor' => $amountMinor,
                'paid_by_user_id' => $order->payer_user_id, // payer may differ from the beneficiary
            ]);

            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }

    /**
     * A paid renewal applies TODAY's commercial terms (B2 / BR-17): the row is re-snapshotted to the current
     * price/term before the new period is computed — so a legacy comp or an old annual row renews as
     * 12 AZN / 30 days, never at its stale snapshot. Rows are only changed once the renewal is paid.
     */
    public function renew(Subscription $subscription, Order $order, int $amountMinor): Subscription
    {
        $terms = $this->prices->commercial($subscription->tier);
        // A14 — an ended subscription restarts from the payment, never back-dated over days without access.
        $period = $this->term->renew($subscription->ends_at->toImmutable(), $terms['term_days'], $this->clock->now());

        return DB::transaction(function () use ($subscription, $order, $amountMinor, $period, $terms): Subscription {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active->value,
                'price_minor' => $terms['price_minor'],
                'term_days' => $terms['term_days'],
                'ends_at' => $period['end'],
                // Y1 — the renewed period gets its own D-7 / D-1 reminders; without this reset the first
                // period's "D-1 already sent" mark silenced every later period's reminders.
                'last_reminder_kind' => null,
                'last_reminder_sent_at' => null,
            ])->save();

            $subscription->periods()->create([
                'order_id' => $order->id,
                'kind' => SubscriptionPeriodKind::Renewal,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'amount_minor' => $amountMinor,
                'paid_by_user_id' => $order->payer_user_id,
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
     * Y5 — an admin moves a resident to another gate: the remaining paid time goes with them, nothing is paid
     * again. The target roster row's subscription (one per row) takes over the same tier, terms and end date and
     * becomes active; the source one is cancelled (`moved_to_device`). Its paid periods stay on the source row as
     * history. Both events fire, so each gate's whitelist follows (complex: subscription-based).
     */
    public function moveTo(Subscription $from, DeviceUser $to): Subscription
    {
        return DB::transaction(function () use ($from, $to): Subscription {
            /** @var Subscription|null $target */
            $target = Subscription::query()->where('device_user_id', $to->getKey())->lockForUpdate()->first();

            $attributes = [
                'tier' => $from->tier->value,
                'price_minor' => (int) $from->price_minor,
                'currency' => $from->currency,
                'term_days' => (int) $from->term_days,
                'starts_at' => $this->clock->now(),
                'ends_at' => $from->ends_at,
                'status' => SubscriptionStatus::Active->value,
                'auto_renew' => false,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                // same end date → the same point in its reminder cycle (Y1)
                'last_reminder_kind' => $from->last_reminder_kind?->value,
                'last_reminder_sent_at' => $from->last_reminder_sent_at,
            ];

            if ($target !== null) {
                $target->forceFill($attributes)->save();
            } else {
                /** @var Subscription $target */
                $target = Subscription::query()->create(['device_user_id' => $to->getKey()] + $attributes);
            }

            $this->cancel($from, 'moved_to_device');
            SubscriptionActivated::dispatch($target);

            return $target;
        });
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
