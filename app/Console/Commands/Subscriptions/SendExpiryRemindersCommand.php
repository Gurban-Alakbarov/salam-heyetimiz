<?php

namespace App\Console\Commands\Subscriptions;

use App\Domain\Subscriptions\Services\ExpirySweep;
use Illuminate\Console\Command;

class SendExpiryRemindersCommand extends Command
{
    protected $signature = 'subscriptions:send-renewal-reminders';

    protected $description = 'Fire D-30/15/7/1 subscription expiry reminders, once per threshold (Tech Spec §13.4).';

    public function handle(ExpirySweep $sweep): int
    {
        $sent = $sweep->sendReminders();

        $this->info(sprintf('reminders_sent=%d', $sent));

        return self::SUCCESS;
    }
}
