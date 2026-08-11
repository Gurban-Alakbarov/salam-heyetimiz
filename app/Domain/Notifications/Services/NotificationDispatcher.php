<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Jobs\SendPushNotificationJob;
use App\Domain\Notifications\Models\Notification;
use App\Support\Time\Clock;

/**
 * Core notification writer (Backend Arch §14.9). For each requested channel it idempotently
 * materialises one [Notification] row — dedupe = (user_id, dedupe_key, channel) within the month
 * partition (R-NOT-05/06) — then enqueues push delivery. An in-app row is the durable inbox item and
 * is `sent` on write; a push row is `queued` and drained by [SendPushNotificationJob], which fans it
 * out to the recipient's installs (multi-device fan-out lives in the job, never as extra rows —
 * R-NOT-08). Callers pass already-rendered copy; this service neither renders templates nor resolves
 * audiences, so business and admin campaigns share the same mechanism without mixing (R-NOT-04).
 */
final class NotificationDispatcher
{
    public function __construct(private readonly Clock $clock) {}

    public function dispatch(NotificationRequest $request): void
    {
        $partitionKey = (int) $this->clock->now()->format('Ym');

        foreach ($request->channels as $channel) {
            $this->materialise($request, $channel, $partitionKey);
        }
    }

    private function materialise(NotificationRequest $request, NotificationChannel $channel, int $partitionKey): void
    {
        $isInapp = $channel === NotificationChannel::Inapp;

        /** @var Notification $notification */
        $notification = Notification::query()->firstOrCreate(
            [
                'user_id' => $request->userId,
                'dedupe_key' => $request->dedupeKey,
                'channel' => $channel->value,
                'partition_key' => $partitionKey,
            ],
            [
                'campaign_id' => $request->campaignId,
                'template_key' => $request->templateKey,
                'payload' => [
                    'title' => $request->title,
                    'body' => $request->body,
                    'type' => $request->type->value,
                    'ids' => $request->ids,
                ],
                // In-app is delivered the moment it is persisted (it IS the inbox item); push waits
                // for the send job. SMS/email rows stay `queued` — their send jobs land in a later
                // phase (channels MVP = push + inapp, INVENTORY §5).
                'status' => $isInapp ? NotificationStatus::Sent->value : NotificationStatus::Queued->value,
                'sent_at' => $isInapp ? $this->clock->now() : null,
            ],
        );

        // Only a freshly-created push row is enqueued — a replay (same dedupe key) finds the existing
        // row and enqueues nothing, keeping delivery exactly-once at the row level (R-NOT-05).
        if ($notification->wasRecentlyCreated && $channel === NotificationChannel::Push) {
            SendPushNotificationJob::dispatch($notification->id, $partitionKey);
        }
    }
}
