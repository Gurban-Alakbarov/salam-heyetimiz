<?php

namespace App\Domain\Notifications\Enums;

/**
 * Per-channel delivery/read state of a [Notification] (DB Arch §7.3). An in-app row is `sent` the
 * instant it is written (it is the inbox item); a push row starts `queued` and [SendPushNotificationJob]
 * flips it to `sent`/`failed`. `read` is set when the user opens the inbox item.
 */
enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Read = 'read';

    /** Reached the user (sent, or subsequently read). */
    public function isDelivered(): bool
    {
        return in_array($this, [self::Sent, self::Read], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Sent, self::Failed, self::Read], true);
    }
}
