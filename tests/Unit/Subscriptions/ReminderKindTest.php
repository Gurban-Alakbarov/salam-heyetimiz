<?php

use App\Domain\Subscriptions\Enums\ReminderKind;

/*
| Tech Spec §13.4 reminder threshold selection and single-fire ranking.
*/

it('maps days-remaining to the correct reminder threshold', function (int $days, ?ReminderKind $expected) {
    expect(ReminderKind::forDaysRemaining($days))->toBe($expected);
})->with([
    'beyond 30 days → none' => [31, null],
    'exactly 30 → D30' => [30, ReminderKind::D30],
    '16 → D30' => [16, ReminderKind::D30],
    'exactly 15 → D15' => [15, ReminderKind::D15],
    '8 → D15' => [8, ReminderKind::D15],
    'exactly 7 → D7' => [7, ReminderKind::D7],
    '2 → D7' => [2, ReminderKind::D7],
    'exactly 1 → D1' => [1, ReminderKind::D1],
    'zero or past → D1' => [0, ReminderKind::D1],
]);

it('ranks thresholds in single-fire progression order', function () {
    expect(ReminderKind::D30->rank())->toBeLessThan(ReminderKind::D15->rank())
        ->and(ReminderKind::D15->rank())->toBeLessThan(ReminderKind::D7->rank())
        ->and(ReminderKind::D7->rank())->toBeLessThan(ReminderKind::D1->rank())
        ->and(ReminderKind::D1->rank())->toBeLessThan(ReminderKind::Expired->rank());
});
