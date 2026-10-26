<?php

namespace App\Domain\Subscriptions\Support;

use Carbon\CarbonImmutable;

/**
 * Term/period boundary math (Tech Spec §13.6). Pure (no Clock).
 *   - Renewed while still active: new ends_at = old ends_at + term_days (no day is lost — the term continues).
 *   - Renewed after it ended: new ends_at = now + term_days. A14 (decided 2026-10-27): there is no "grace"
 *     back-dating any more — the days between expiry and payment had no access (opening needs ends_at in the
 *     future), so the resident never pays for them.
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
    public function renew(CarbonImmutable $currentEndsAt, int $termDays, CarbonImmutable $now): array
    {
        if ($now->lessThan($currentEndsAt)) {
            return ['start' => $currentEndsAt, 'end' => $currentEndsAt->addDays($termDays)];
        }

        return ['start' => $now, 'end' => $now->addDays($termDays)];
    }
}
