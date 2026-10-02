<?php

namespace App\Domain\Subscriptions\Jobs;

use App\Domain\Subscriptions\Actions\SweepAbandonedSubscriptionIntents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Hourly: never-paid complex subscribe intents → cancelled + roster row revoked (IMPLEMENTATION_PLAN B7). */
class SweepAbandonedSubscriptionIntentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(SweepAbandonedSubscriptionIntents $sweep): int
    {
        return $sweep->handle();
    }
}
