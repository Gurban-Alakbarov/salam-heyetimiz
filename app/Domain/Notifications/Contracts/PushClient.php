<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Enums\PushDeliveryStatus;

/**
 * Provider-agnostic push transport (R-ARCH-08). The concrete FCM adapter (FcmPushClient) binds in the
 * real environment once credentials land (Phase 4); [FakePushClient] backs it until then and in tests.
 * The transport never sees the notification row — only the rendered message + deep-link data, and never
 * secrets/PII in `data` (R-NOT-17).
 */
interface PushClient
{
    /**
     * Deliver one hybrid message to a single device token.
     *
     * @param  array{title: string, body: string}  $notification  the FCM `notification` block
     * @param  array{type: string, notification_id: string, ids: array<string, mixed>}  $data  the FCM `data` block
     */
    public function send(string $token, array $notification, array $data): PushDeliveryStatus;
}
