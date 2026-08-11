<?php

namespace App\Domain\Subscriptions\Support;

use Carbon\CarbonImmutable;

/**
 * Term/period boundary math (Tech Spec §13.6). Pure (no Clock).
 *   - Renewed before expiry (or within the grace window): new ends_at = old ends_at + term_days
 *     (no retroactive credit — the term simply continues).
 *   - Renewed after expiry + grace: new ends_at = now + term_days.
 */
final class SubscriptionTerm
{
    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function initial(int $termDays, CarbonImmutable $now): array
    {
        return ['start' => $now, 'end' => $now->addDays($termDays)];
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function renew(CarbonImmutable $currentEndsAt, int $termDays, int $graceDays, CarbonImmutable $now): array
    {
        if ($now->lessThanOrEqualTo($currentEndsAt->addDays($graceDays))) {
            return ['start' => $currentEndsAt, 'end' => $currentEndsAt->addDays($termDays)];
        }

        return ['start' => $now, 'end' => $now->addDays($termDays)];
    }
}
