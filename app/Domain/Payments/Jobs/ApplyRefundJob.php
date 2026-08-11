<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\ApplyRefund;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Services\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ApplyRefundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public int $refundId)
    {
        $this->onQueue('payments');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 7200];
    }

    public function handle(ApplyRefund $action): void
    {
        $refund = Refund::query()->find($this->refundId);

        if ($refund !== null) {
            $action->handle($refund);
        }
    }

    public function failed(Throwable $exception): void
    {
        $refund = Refund::query()->find($this->refundId);

        if ($refund !== null) {
            app(RefundService::class)->markFailed($refund, 'refund_job_failed: '.$exception->getMessage());
        }
    }
}
