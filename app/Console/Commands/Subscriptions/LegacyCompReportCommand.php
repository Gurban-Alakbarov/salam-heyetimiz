<?php

namespace App\Console\Commands\Subscriptions;

use App\Domain\Subscriptions\Models\Subscription;
use Illuminate\Console\Command;

/**
 * READ-ONLY reconciliation report for legacy comp subscriptions (IMPLEMENTATION_PLAN B2 / BR-17). A legacy
 * comp is derived — no column: price_minor = 0 AND no paid (positive) subscription_period. The report never
 * writes: it does not re-price, convert, cancel or charge anything. Comps stay as they are until their
 * ends_at; a user-initiated paid renewal is the only thing that moves them onto 12 AZN / 30 days.
 */
class LegacyCompReportCommand extends Command
{
    protected $signature = 'subscriptions:legacy-report {--list : List each legacy comp subscription}';

    protected $description = 'Read-only report of legacy comp (free) subscriptions — never modifies data.';

    public function handle(): int
    {
        $comps = Subscription::query()
            ->where('price_minor', 0)
            ->whereDoesntHave('periods', fn ($q) => $q->where('amount_minor', '>', 0))
            ->orderBy('ends_at')
            ->get(['id', 'device_user_id', 'tier', 'status', 'term_days', 'ends_at']);

        $now = now();
        $this->info(sprintf('legacy_comp_total=%d (read-only; nothing was changed)', $comps->count()));

        $rows = $comps->groupBy(fn (Subscription $s): string => $s->tier->value.'/'.$s->status->value)
            ->map(fn ($group, string $key): array => [
                $key,
                $group->count(),
                $group->filter(fn (Subscription $s): bool => $s->ends_at !== null && $s->ends_at->lessThanOrEqualTo($now->copy()->addDays(30)))->count(),
                optional($group->min('ends_at'))->toDateString(),
                optional($group->max('ends_at'))->toDateString(),
            ])->values()->all();

        $this->table(['tier/status', 'count', 'ending_within_30d', 'earliest_end', 'latest_end'], $rows);

        if ($this->option('list')) {
            $this->table(
                ['id', 'device_user_id', 'tier', 'status', 'term_days', 'ends_at'],
                $comps->map(fn (Subscription $s): array => [$s->id, $s->device_user_id, $s->tier->value, $s->status->value, $s->term_days, optional($s->ends_at)->toDateTimeString()])->all(),
            );
        }

        return self::SUCCESS;
    }
}
