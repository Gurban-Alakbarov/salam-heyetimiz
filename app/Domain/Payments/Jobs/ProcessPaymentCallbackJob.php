<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\ProcessPaymentCallback;
use App\Domain\Payments\Models\PaymentCallback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaymentCallbackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public int $callbackId)
    {
        $this->onQueue('payments');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 7200];
    }

    public function handle(ProcessPaymentCallback $action): void
    {
        $callback = PaymentCallback::query()->find($this->callbackId);

        if ($callback !== null) {
            $action->handle($callback);
        }
    }
}
