<?php

namespace App\Domain\Subscriptions\Enums;

/**
 * Expiry-reminder thresholds (Tech Spec §13.4). `rank()` enforces single-fire progression
 * (d30 → d15 → d7 → d1 → expired) via subscriptions.last_reminder_kind.
 */
enum ReminderKind: string
{
    case D30 = 'd30';
    case D15 = 'd15';
    case D7 = 'd7';
    case D1 = 'd1';
    case Expired = 'expired';

    public function rank(): int
    {
        return match ($this) {
            self::D30 => 1,
            self::D15 => 2,
            self::D7 => 3,
            self::D1 => 4,
            self::Expired => 5,
        };
    }

    /** The reminder threshold for a given days-remaining value, or null if > 30 days out. */
    public static function forDaysRemaining(int $daysRemaining): ?self
    {
        return match (true) {
            $daysRemaining <= 1 => self::D1,
            $daysRemaining <= 7 => self::D7,
            $daysRemaining <= 15 => self::D15,
            $daysRemaining <= 30 => self::D30,
            default => null,
        };
    }
}
