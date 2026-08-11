<?php

namespace App\Domain\Notifications\DTOs;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;

/**
 * One notification intent handed to [NotificationDispatcher]: a single recipient, the already-rendered
 * copy, the deep-link ids, the target channels, and the idempotency key. Business callers pass the real
 * template key with `campaignId = null`; admin campaign fan-out passes `system.admin_campaign` + the
 * campaign id. The dispatcher owns row creation + push enqueue — it neither renders templates nor
 * resolves audiences, so the business and admin paths stay separate (R-NOT-04).
 */
final class NotificationRequest
{
    /**
     * @param  list<NotificationChannel>  $channels  channels to materialise (each → one row)
     * @param  array<string, mixed>  $ids  deep-link entity ids, e.g. ['visitor_link_id' => 5, 'device_id' => 3]
     */
    public function __construct(
        public readonly int $userId,
        public readonly NotificationType $type,
        public readonly string $templateKey,
        public readonly string $title,
        public readonly string $body,
        public readonly array $ids,
        public readonly array $channels,
        public readonly string $dedupeKey,
        public readonly ?int $campaignId = null,
    ) {}
}
