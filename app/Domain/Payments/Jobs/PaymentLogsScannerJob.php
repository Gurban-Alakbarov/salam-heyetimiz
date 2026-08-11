<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Models\PaymentLog;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily defence-in-depth scan of recent payment_logs for PAN-/CVV-like patterns that the allowlist
 * serializer should already prevent (HIGH-15 / R-PAY-10). Any hit fires a P1 alert.
 */
class PaymentLogsScannerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(Clock $clock): int
    {
        $since = $clock->now()->subDays(7);
        $hits = 0;

        PaymentLog::query()
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->chunkById(500, function ($logs) use (&$hits): void {
                foreach ($logs as $log) {
                    foreach (['request_redacted_encrypted', 'response_redacted_encrypted'] as $column) {
                        $value = (string) ($log->{$column} ?? '');
                        if ($value === '') {
                            continue;
                        }

                        // 13–19 consecutive digits (a raw PAN) or an explicit CVV field.
                        if (preg_match('/\d{13,19}/', $value) === 1 || preg_match('/cvv|cvc|cvv2/i', $value) === 1) {
                            $hits++;
                            Log::alert('payment_logs: PAN/CVV-like pattern detected', [
                                'payment_log_id' => $log->id,
                                'column' => $column,
                            ]);
                        }
                    }
                }
            });

        return $hits;
    }
}
