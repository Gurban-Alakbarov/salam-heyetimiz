<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Subscriptions\Enums\ReminderKind;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Events\SubscriptionExpiringSoon;
use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Time\Clock;

/**
 * Daily sweeps (Tech Spec §13.4–13.5): expire active subscriptions past ends_at, and fire D-30/15/7/1
 * reminders exactly once per threshold (progression tracked by last_reminder_kind). Whitelist removal
 * and device-status re-derivation are DeviceComm reactions to SubscriptionExpired (batch 07).
 */
final class ExpirySweep
{
    public function __construct(
        private readonly Clock $clock,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** Expire active subscriptions whose term has ended. Returns the count expired. */
    public function expireBatch(): int
    {
        $now = $this->clock->now();
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '<', $now)
            ->orderBy('id')
            ->chunkById(500, function ($subscriptions) use (&$count): void {
                foreach ($subscriptions as $subscription) {
                    $this->subscriptions->expire($subscription);
                    $count++;
                }
            });

        return $count;
    }

    /** Fire expiry reminders at the configured thresholds. Returns the count of reminders sent. */
    public function sendReminders(): int
    {
        $now = $this->clock->now();
        $horizon = $now->addDays(30);
        $sent = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereBetween('ends_at', [$now, $horizon])
            ->orderBy('id')
            ->chunkById(500, function ($subscriptions) use ($now, &$sent): void {
                foreach ($subscriptions as $subscription) {
                    $daysRemaining = (int) ceil(($subscription->ends_at->getTimestamp() - $now->getTimestamp()) / 86400);
                    $kind = ReminderKind::forDaysRemaining($daysRemaining);

                    if ($kind === null) {
                        continue;
                    }

                    $lastRank = $subscription->last_reminder_kind?->rank() ?? 0;
                    if ($kind->rank() <= $lastRank) {
                        continue; // this threshold (or a later one) already sent
                    }

                    $subscription->forceFill([
                        'last_reminder_kind' => $kind->value,
                        'last_reminder_sent_at' => $now,
                    ])->save();

                    SubscriptionExpiringSoon::dispatch($subscription, $kind);
                    $sent++;
                }
            });

        return $sent;
    }
}
