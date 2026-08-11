<?php

namespace App\Console\Commands\Payments;

use App\Domain\Payments\Services\OrderReconciler;
use Illuminate\Console\Command;

class ReconcileOrdersCommand extends Command
{
    protected $signature = 'payments:reconcile-orders';

    protected $description = 'Reconcile stale authorising orders against the bank and expire timed-out orders (R-PAY-13).';

    public function handle(OrderReconciler $reconciler): int
    {
        $result = $reconciler->reconcile();

        $this->info(sprintf(
            'rechecked=%d resolved=%d expired=%d',
            $result['rechecked'],
            $result['resolved'],
            $result['expired'],
        ));

        return self::SUCCESS;
    }
}
