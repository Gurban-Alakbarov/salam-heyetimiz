<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\RecheckOrder;
use App\Domain\Payments\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Re-checks a PENDING/authorising order against the bank with backoff (R-PAY-05/13). */
class RecheckOrderStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public int $orderId)
    {
        $this->onQueue('payments');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 7200];
    }

    public function handle(RecheckOrder $action): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order !== null) {
            $action->handle($order);
        }
    }
}
