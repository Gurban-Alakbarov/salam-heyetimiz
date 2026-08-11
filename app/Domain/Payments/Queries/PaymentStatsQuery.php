<?php

namespace App\Domain\Payments\Queries;

use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\Refund;
use App\Support\Time\Clock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Aggregated payment statistics for the admin dashboard (back-office overview). */
final class PaymentStatsQuery
{
    private const PAID_LIKE = ['paid', 'partially_refunded', 'refunded'];

    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $now = $this->clock->now();
        $today = $now->copy()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $week = $now->copy()->startOfWeek();
        $month = $now->copy()->startOfMonth();

        $statusCounts = Order::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();
        $cnt = static fn (string $s): int => (int) ($statusCounts[$s] ?? 0);

        $paidCount = $cnt('paid') + $cnt('partially_refunded') + $cnt('refunded');
        $terminal = $paidCount + $cnt('failed') + $cnt('cancelled') + $cnt('expired');
        $refundedCount = $cnt('refunded') + $cnt('partially_refunded');

        return [
            'periods' => [
                'today' => $this->paidIn($today),
                'yesterday' => $this->paidIn($yesterday, $today),
                'this_week' => $this->paidIn($week),
                'this_month' => $this->paidIn($month),
            ],
            'totals' => [
                'paid' => $paidCount,
                'pending' => $cnt('pending'),
                'authorising' => $cnt('authorising'),
                'failed' => $cnt('failed'),
                'cancelled' => $cnt('cancelled'),
                'refunded' => $cnt('refunded'),
                'partially_refunded' => $cnt('partially_refunded'),
                'total_paid_minor' => (int) Order::query()->whereIn('status', self::PAID_LIKE)->sum('amount_minor'),
                'total_refunded_minor' => (int) abs((int) Payment::query()->where('type', 'refund')->sum('amount_minor')),
            ],
            'rates' => [
                'success_rate' => $terminal > 0 ? round($paidCount / $terminal * 100, 1) : null,
                'refund_rate' => $paidCount > 0 ? round($refundedCount / $paidCount * 100, 1) : null,
            ],
            'avg_payment_seconds' => $this->avgSeconds(Order::query()->whereNotNull('paid_at'), 'created_at', 'paid_at'),
            'avg_refund_seconds' => $this->avgSeconds(Refund::query()->where('status', 'approved')->whereNotNull('completed_at'), 'created_at', 'completed_at'),
        ];
    }

    /**
     * @return array{count: int, amount_minor: int}
     */
    private function paidIn(\Carbon\CarbonInterface $from, ?\Carbon\CarbonInterface $to = null): array
    {
        $base = Order::query()->whereIn('status', self::PAID_LIKE)->where('paid_at', '>=', $from)
            ->when($to !== null, fn ($q) => $q->where('paid_at', '<', $to));

        return ['count' => (clone $base)->count(), 'amount_minor' => (int) (clone $base)->sum('amount_minor')];
    }

    private function avgSeconds(Builder $query, string $from, string $to): ?int
    {
        // ABS guards against backdated demo/seed rows where the completion timestamp precedes creation.
        $avg = $query->avg(DB::raw("ABS(TIMESTAMPDIFF(SECOND, {$from}, {$to}))"));

        return $avg !== null ? (int) round((float) $avg) : null;
    }
}
