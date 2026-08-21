<?php

use App\Domain\Subscriptions\Support\RefundProration;
use Carbon\CarbonImmutable;

/*
| Tech Spec §14.5.1 pro-rata refund-to-time math. Pure calculator; no DB.
*/

beforeEach(function (): void {
    $this->proration = new RefundProration();
});

it('matches the documented worked example (12 AZN sub, 4 AZN refund)', function () {
    // price_per_day = floor(1200/365) = 3; days_to_remove = floor(400/3) = 133;
    // new_ends_at = 2027-01-01 − 133d = 2026-08-21.
    $result = $this->proration->calculate(
        priceMinor: 1200,
        termDays: 365,
        refundAmountMinor: 400,
        currentEndsAt: CarbonImmutable::parse('2027-01-01T00:00:00Z'),
        now: CarbonImmutable::parse('2026-04-01T00:00:00Z'),
    );

    expect($result->fullRefund)->toBeFalse()
        ->and($result->daysToRemove)->toBe(133)
        ->and($result->newEndsAt->toDateString())->toBe('2026-08-21');
});

it('falls through to a full refund when the price-per-day floors to zero', function () {
    // price_minor (100) < term_days (365) → price_per_day = 0 → full revoke at now.
    $now = CarbonImmutable::parse('2026-04-01T00:00:00Z');

    $result = $this->proration->calculate(
        priceMinor: 100,
        termDays: 365,
        refundAmountMinor: 50,
        currentEndsAt: CarbonImmutable::parse('2027-01-01T00:00:00Z'),
        now: $now,
    );

    expect($result->fullRefund)->toBeTrue()
        ->and($result->newEndsAt->equalTo($now))->toBeTrue();
});

it('becomes a full revoke when removing the days zeroes the remaining term', function () {
    // Large refund removes more days than remain → new_ends_at <= now → full.
    $now = CarbonImmutable::parse('2026-12-01T00:00:00Z');

    $result = $this->proration->calculate(
        priceMinor: 1200,
        termDays: 365,
        refundAmountMinor: 1200,
        currentEndsAt: CarbonImmutable::parse('2027-01-01T00:00:00Z'),
        now: $now,
    );

    expect($result->fullRefund)->toBeTrue()
        ->and($result->newEndsAt->equalTo($now))->toBeTrue();
});
