<?php

namespace App\Domain\Subscriptions\Jobs;

use App\Domain\Subscriptions\Services\ExpirySweep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendRenewalRemindersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(ExpirySweep $sweep): int
    {
        return $sweep->sendReminders();
    }
}
