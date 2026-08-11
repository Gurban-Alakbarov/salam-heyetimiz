<?php

namespace App\Domain\Subscriptions\Queries;

use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Pagination\Cursor;
use App\Support\Time\Clock;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read model for subscription listings with cursor pagination (R-API-06; BACKEND §5.1 query object).
 */
final class SubscriptionQuery
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function forUser(int $userId, ?string $status, int $limit, ?int $cursor): array
    {
        $query = Subscription::query()
            ->with('deviceUser')
            ->whereHas('deviceUser', fn (Builder $q) => $q->where('user_id', $userId));

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginate($query, $limit, $cursor);
    }

    /** The subscription owned by a specific roster row (device_user_id is UNIQUE — R-DOM-03). */
    public function forDeviceUser(int $deviceUserId): ?Subscription
    {
        return Subscription::query()->where('device_user_id', $deviceUserId)->first();
    }

    /** The caller's subscription for a device, resolved via their active roster row. */
    public function forUserDevice(int $userId, int $deviceId): ?Subscription
    {
        return Subscription::query()
            ->whereHas('deviceUser', fn (Builder $q) => $q
                ->where('device_id', $deviceId)
                ->where('user_id', $userId)
                ->where('status', 'active'))
            ->first();
    }

    /**
     * Does the user hold any currently-active subscription (any of their devices)? Real-time
     * check (status=active AND ends_at in the future) — the read for UserSelf.has_active_subscription.
     */
    public function hasActiveForUser(int $userId): bool
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '>', $this->clock->now())
            ->whereHas('deviceUser', fn (Builder $q) => $q->where('user_id', $userId))
            ->exists();
    }

    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(?string $status, ?int $expiresWithinDays, int $limit, ?int $cursor): array
    {
        $query = Subscription::query()->with('deviceUser');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($expiresWithinDays !== null) {
            $now = $this->clock->now();
            $query->where('status', SubscriptionStatus::Active->value)
                ->whereBetween('ends_at', [$now, $now->addDays($expiresWithinDays)]);
        }

        return $this->paginate($query, $limit, $cursor);
    }

    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    private function paginate(Builder $query, int $limit, ?int $cursor): array
    {
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
}
