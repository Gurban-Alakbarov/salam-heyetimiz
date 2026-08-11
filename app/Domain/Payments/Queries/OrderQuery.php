<?php

namespace App\Domain\Payments\Queries;

use App\Domain\Payments\Models\Order;
use App\Support\Pagination\Cursor;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read model for order listings with cursor pagination (R-API-06). Serves both the mobile
 * (payer-scoped) and admin (filtered) list endpoints. This is the "repository" role per
 * BACKEND §5.1 — a shaped read object, not a generic Eloquent wrapper.
 */
final class OrderQuery
{
    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function forUser(int $userId, ?string $status, int $limit, ?int $cursor): array
    {
        $query = Order::query()->with('items')->where('payer_user_id', $userId);
        $this->applyStatus($query, $status);

        return $this->paginate($query, $limit, $cursor);
    }

    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(
        ?string $status,
        ?string $purpose,
        ?int $payerUserId,
        ?string $since,
        ?string $until,
        int $limit,
        ?int $cursor,
        ?string $q = null,
    ): array {
        $query = Order::query()->with(['items', 'payer:id,phone,email', 'payments']);
        $this->applyStatus($query, $status);

        if ($purpose !== null) {
            $query->where('purpose', $purpose);
        }
        if ($payerUserId !== null) {
            $query->where('payer_user_id', $payerUserId);
        }
        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }
        if ($until !== null) {
            $query->where('created_at', '<=', $until);
        }
        if ($q !== null && $q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('reference', 'like', "%{$q}%")
                    ->orWhere('bank_order_id', 'like', "%{$q}%")
                    ->orWhereHas('payer', fn ($p) => $p->where('phone', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            });
        }

        return $this->paginate($query, $limit, $cursor);
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status !== null) {
            $query->where('status', $status);
        }
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
