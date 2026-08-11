<?php

namespace App\Domain\Notifications\Enums;

/**
 * Delivery channels for a notification (DB Arch §7.3). Each selected channel materialises its own
 * [Notification] row. A template's `default_channels_mask` packs these as bit flags
 * (push=1/sms=2/inapp=4/email=8 — DB Arch §7.1). MVP delivery covers push + inapp (INVENTORY §5);
 * sms/email rows are catalogued but their send jobs land in a later phase.
 */
enum NotificationChannel: string
{
    case Push = 'push';
    case Sms = 'sms';
    case Inapp = 'inapp';
    case Email = 'email';

    /** Bit flag for this channel in a template's `default_channels_mask`. */
    public function bit(): int
    {
        return match ($this) {
            self::Push => 1,
            self::Sms => 2,
            self::Inapp => 4,
            self::Email => 8,
        };
    }

    /**
     * Decode a `default_channels_mask` into its channels, in stable enum order.
     *
     * @return list<self>
     */
    public static function fromMask(int $mask): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $channel): bool => ($mask & $channel->bit()) === $channel->bit(),
        ));
    }
}
