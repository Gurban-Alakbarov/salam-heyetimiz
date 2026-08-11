<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Services\RefundService;

final class ApplyRefund
{
    public function __construct(private readonly RefundService $refunds) {}

    public function handle(Refund $refund): void
    {
        $this->refunds->executeApprovedRefund($refund);
    }
}
