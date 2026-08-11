<?php

namespace App\Console\Commands\Subscriptions;

use App\Domain\Subscriptions\Services\ExpirySweep;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire active subscriptions whose term has ended (Tech Spec §13.5 daily sweep).';

    public function handle(ExpirySweep $sweep): int
    {
        $expired = $sweep->expireBatch();

        $this->info(sprintf('expired=%d', $expired));

        return self::SUCCESS;
    }
}
