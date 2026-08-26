<?php

namespace App\Domain\Visitor\Queries;

use App\Domain\Visitor\Models\VisitorLink;
use App\Support\Pagination\Cursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model for the admin visitor-links directory: filter by device / derived status / creator / purpose
 * / access type / name / date range, complex-scoped for complex_manager, cursor-paginated by id. Status is
 * derived from revoked_at / expires_at / usage, so those filters mirror VisitorLink's predicates in SQL.
 */
final class VisitorLinkQuery
{
    /**
     * @return array{data: Collection<int, VisitorLink>, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(VisitorLinkListFilters $filters, ?int $scopeComplexId, int $limit, ?int $cursor): array
    {
        $now = Carbon::now();

        $query = VisitorLink::query()
            ->with(['device', 'createdByUser', 'createdByAdmin'])
            ->when($filters->deviceId !== null, fn (Builder $q) => $q->where('device_id', $filters->deviceId))
            ->when($scopeComplexId !== null, fn (Builder $q) => $q->whereHas('device', fn (Builder $d) => $d->where('complex_id', $scopeComplexId)))
            ->when($cursor !== null, fn (Builder $q) => $q->where('id', '<', $cursor));

        $this->applyStatus($query, $filters->status, $now);
        $this->applyFilters($query, $filters);

        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return [
            'data' => $data,
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->getKey()) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * The caller's OWN visitor links across every device — the resident "Dəvətlərim / My invitations"
     * listing (GET /v1/visitor-links). Ownership is enforced HERE (created_by_user_id), never in the
     * client. Status is derived (active/used/expired/revoked) and mirrors VisitorLink's predicates in SQL,
     * so the filter and the per-row statusLabel always agree. `first_used_at` is the earliest recorded
     * usage (a MIN subquery — no N+1). Cursor-paginated by id, exactly like the admin directory.
     *
     * @return array{data: Collection<int, VisitorLink>, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function forUser(int $userId, ?string $status, int $limit, ?int $cursor): array
    {
        $now = Carbon::now();

        $query = VisitorLink::query()
            ->where('created_by_user_id', $userId)
            ->withMin('usages as first_used_at', 'used_at')
            ->when($cursor !== null, fn (Builder $q) => $q->where('id', '<', $cursor));

        $this->applyStatus($query, $status, $now);

        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return [
            'data' => $data,
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->getKey()) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * @param  Builder<VisitorLink>  $query
     */
    private function applyStatus(Builder $query, ?string $status, Carbon $now): void
    {
        match ($status) {
            'revoked' => $query->whereNotNull('revoked_at'),
            'expired' => $query->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', $now),
            // Usage-exhausted but not yet revoked/expired — matches VisitorLink::statusLabel()'s 'used_up'.
            'used' => $query->whereNull('revoked_at')
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->whereNotNull('max_usage')->whereColumn('usage_count', '>=', 'max_usage'),
            'active' => $query->whereNull('revoked_at')
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->where(fn (Builder $q) => $q->whereNull('max_usage')->orWhereColumn('usage_count', '<', 'max_usage')),
            default => null, // all
        };
    }

    /**
     * @param  Builder<VisitorLink>  $query
     */
    private function applyFilters(Builder $query, VisitorLinkListFilters $filters): void
    {
        $query
            ->when($filters->q !== null && $filters->q !== '', fn (Builder $q) => $q->where('visitor_name', 'like', '%'.$filters->q.'%'))
            ->when($filters->purpose !== null, fn (Builder $q) => $q->where('purpose', $filters->purpose))
            ->when($filters->accessType !== null, fn (Builder $q) => $q->where('access_type', $filters->accessType))
            ->when($filters->createdBy === 'admin', fn (Builder $q) => $q->whereNotNull('created_by_admin_id'))
            ->when($filters->createdBy === 'resident', fn (Builder $q) => $q->whereNotNull('created_by_user_id'))
            ->when($filters->createdFrom !== null, fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters->createdFrom)->startOfDay()))
            ->when($filters->createdTo !== null, fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters->createdTo)->endOfDay()));
    }
}
