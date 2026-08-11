<?php

namespace App\Domain\Subscriptions\Support;

use Carbon\CarbonImmutable;

/**
 * Pro-rata refund-to-time math (Tech Spec §14.5.1). Pure (no Clock) so it is trivially testable
 * against the documented worked example. price_per_day is floored (conservative for the customer);
 * if removing the days zeroes the remaining term, it falls through to full-refund semantics.
 *
 * Worked example: 12 AZN main sub, 365-day term, ends 2027-01-01; 4 AZN refund on 2026-04-01.
 *   price_per_day = floor(1200/365) = 3 qəpik
 *   days_to_remove = floor(400/3) = 133
 *   new_ends_at    = max(now, 2027-01-01 − 133d) = 2026-08-21
 */
final class RefundProration
{
    public function calculate(
        int $priceMinor,
        int $termDays,
        int $refundAmountMinor,
        CarbonImmutable $currentEndsAt,
        CarbonImmutable $now,
    ): RefundProrationResult {
        $pricePerDayMinor = $termDays > 0 ? intdiv($priceMinor, $termDays) : 0;

        if ($pricePerDayMinor <= 0) {
            return new RefundProrationResult(true, 0, $now);
        }

        $daysToRemove = intdiv($refundAmountMinor, $pricePerDayMinor);
        $newEndsAt = $currentEndsAt->subDays($daysToRemove);

        if ($newEndsAt->lessThanOrEqualTo($now)) {
            return new RefundProrationResult(true, $daysToRemove, $now);
        }

        return new RefundProrationResult(false, $daysToRemove, $newEndsAt);
    }
}
