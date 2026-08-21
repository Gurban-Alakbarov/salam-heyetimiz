<?php

use App\Domain\Subscriptions\Support\SubscriptionTerm;
use Carbon\CarbonImmutable;

/*
| Tech Spec §13.6 term/period boundary math. Pure; no DB.
*/

beforeEach(function (): void {
    $this->term = new SubscriptionTerm();
});

it('opens an initial term of term_days from now', function () {
    $now = CarbonImmutable::parse('2026-01-01T00:00:00Z');

    $period = $this->term->initial(365, $now);

    expect($period['start']->toDateString())->toBe('2026-01-01')
        ->and($period['end']->toDateString())->toBe('2027-01-01');
});

it('extends from the old ends_at when renewed before expiry (no retroactive credit)', function () {
    $endsAt = CarbonImmutable::parse('2026-06-20T00:00:00Z');
    $now = CarbonImmutable::parse('2026-06-15T00:00:00Z');

    $period = $this->term->renew($endsAt, 365, 7, $now);

    expect($period['start']->toDateString())->toBe('2026-06-20')
        ->and($period['end']->toDateString())->toBe('2027-06-20');
});

it('extends from the old ends_at when renewed within the grace window', function () {
    $endsAt = CarbonImmutable::parse('2026-06-01T00:00:00Z');
    $now = CarbonImmutable::parse('2026-06-06T00:00:00Z'); // 5 days late, grace is 7

    $period = $this->term->renew($endsAt, 365, 7, $now);

    expect($period['start']->toDateString())->toBe('2026-06-01')
        ->and($period['end']->toDateString())->toBe('2027-06-01');
});

it('restarts from now when renewed after expiry + grace', function () {
    $endsAt = CarbonImmutable::parse('2026-06-01T00:00:00Z');
    $now = CarbonImmutable::parse('2026-07-01T00:00:00Z'); // well past grace

    $period = $this->term->renew($endsAt, 365, 7, $now);

    expect($period['start']->toDateString())->toBe('2026-07-01')
        ->and($period['end']->toDateString())->toBe('2027-07-01');
});
