<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Models\Notification;
use App\Support\Time\Clock;

/**
 * In-app inbox read-state mutations (openapi markNotificationRead / markAllNotificationsRead). Only the
 * caller's own `inapp` rows are touched. Marking is idempotent — an already-read item stays read (its
 * original read_at is preserved). Operates on the partitioned notifications table by id + user_id.
 */
final class NotificationInboxService
{
    public function __construct(private readonly Clock $clock) {}

    /** Mark one in-app notification read for the user. Returns false when it does not exist / not owned. */
    public function markRead(int $userId, int $notificationId): bool
    {
        $exists = $this->owned($userId, $notificationId)->exists();
        if (! $exists) {
            return false;
        }

        $this->owned($userId, $notificationId)
            ->whereNull('read_at')
            ->update([
                'status' => NotificationStatus::Read->value,
                'read_at' => $this->clock->now(),
            ]);

        return true;
    }

    /** Mark every unread in-app notification read for the user (idempotent). */
    public function markAllRead(int $userId): void
    {
        Notification::query()
            ->where('user_id', $userId)
            ->where('channel', NotificationChannel::Inapp->value)
            ->whereNull('read_at')
            ->update([
                'status' => NotificationStatus::Read->value,
                'read_at' => $this->clock->now(),
            ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Notification> */
    private function owned(int $userId, int $notificationId): \Illuminate\Database\Eloquent\Builder
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('id', $notificationId)
            ->where('channel', NotificationChannel::Inapp->value);
    }
}
