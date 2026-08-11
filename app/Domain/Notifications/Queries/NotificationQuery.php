<?php

namespace App\Domain\Notifications\Queries;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Models\Notification;
use App\Support\Pagination\Cursor;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * In-app inbox read model with cursor pagination (R-API-06). The inbox shows the durable `inapp` rows
 * only — `push`/`sms`/`email` rows are delivery records, not inbox items (DB Arch §7.3). Scoped to the
 * caller; a user never sees another user's notifications. `id`-descending = newest first (monotonic id).
 */
final class NotificationQuery
{
    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function inboxForUser(int $userId, ?string $status, int $limit, ?int $cursor): array
    {
        $query = $this->base($userId);

        // status filter is the OpenAPI enum [sent, read]; anything else is ignored (returns all).
        if ($status === NotificationStatus::Sent->value || $status === NotificationStatus::Read->value) {
            $query->where('status', $status);
        }
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return [
            'data' => $data,
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->id) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ];
    }

    /** Unread in-app notifications for the badge — inapp rows not yet read. */
    public function unreadCount(int $userId): int
    {
        return $this->base($userId)->where('status', NotificationStatus::Sent->value)->count();
    }

    /** @return Builder<Notification> */
    private function base(int $userId): Builder
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('channel', NotificationChannel::Inapp->value);
    }
}
