<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Notifications\Contracts\PushClient;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\PushDeliveryStatus;
use App\Domain\Notifications\Models\Notification;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Drains a queued push [Notification] off the request path onto the `notifications` Horizon queue and
 * fans it out to the recipient's active installs (Backend Arch §14.9). Carries ids only (notifications
 * is partitioned). It builds the hybrid FCM message from the row's `payload` and sends once per device
 * via [PushClient]; a token FCM reports unregistered is soft-invalidated (user_devices.push_invalid,
 * R-NOT-14) without aborting the fan-out to the other installs. At least one delivery → `sent`,
 * otherwise `failed`. A missing / non-push / already-delivered row is a no-op, so retries are idempotent.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $notificationId,
        public readonly int $partitionKey,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(PushClient $pushClient, UserDeviceService $devices, Clock $clock): void
    {
        $notification = Notification::query()
            ->where('partition_key', $this->partitionKey)
            ->whereKey($this->notificationId)
            ->first();

        if ($notification === null || $notification->channel !== NotificationChannel::Push) {
            return; // gone, or not a push row — nothing to deliver
        }

        if ($notification->status->isDelivered()) {
            return; // already sent/read on an earlier attempt — do not re-send
        }

        $payload = $notification->payload ?? [];
        $message = [
            'title' => (string) ($payload['title'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
        ];
        $data = [
            'type' => (string) ($payload['type'] ?? ''),
            'notification_id' => (string) $notification->getKey(),
            'ids' => (array) ($payload['ids'] ?? []),
        ];

        $targets = $devices->activePushTargets((int) $notification->user_id);

        if ($targets->isEmpty()) {
            $this->markFailed($notification, 'no_active_device');

            return;
        }

        $delivered = false;
        $failureReason = null;

        foreach ($targets as $device) {
            try {
                $outcome = $pushClient->send((string) $device->push_token, $message, $data);
            } catch (Throwable) {
                // A single transport error must not abort the fan-out to the other installs.
                $failureReason = 'push_error';

                continue;
            }

            if ($outcome === PushDeliveryStatus::Delivered) {
                $delivered = true;
            } elseif ($outcome === PushDeliveryStatus::TokenInvalid) {
                $devices->markPushInvalid($device); // re-activated on the next login/token refresh
                $failureReason = 'push_invalid';
            } else {
                $failureReason = 'push_failed';
            }
        }

        if ($delivered) {
            $notification->forceFill([
                'status' => NotificationStatus::Sent->value,
                'sent_at' => $clock->now(),
                'failure_reason' => null,
            ])->save();

            return;
        }

        $this->markFailed($notification, $failureReason ?? 'push_failed');
    }

    private function markFailed(Notification $notification, string $reason): void
    {
        $notification->forceFill([
            'status' => NotificationStatus::Failed->value,
            'failure_reason' => substr($reason, 0, 255),
        ])->save();
    }
}
