<?php

namespace App\Domain\Notifications\Enums;

/**
 * Outcome of one push send to a single device token, returned by [PushClient::send]. `TokenInvalid`
 * maps FCM's UNREGISTERED/invalid-token result — the delivery job soft-flags the install
 * (`user_devices.push_invalid`, R-NOT-14); `Failed` is a transient error the job treats as non-fatal
 * per device. Delivery outcome, not persisted state (that is [NotificationStatus]).
 */
enum PushDeliveryStatus: string
{
    case Delivered = 'delivered';
    case Failed = 'failed';
    case TokenInvalid = 'token_invalid';
}
