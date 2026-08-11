<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Payments\DTOs\RefundRequestData;
use App\Domain\Payments\Jobs\ApplyRefundJob;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Services\RefundService;

final class RequestRefund
{
    public function __construct(private readonly RefundService $refunds) {}

    public function handle(Order $order, RefundRequestData $data, AdminUser $admin): Refund
    {
        $refund = $this->refunds->request($order, $data, $admin);

        ApplyRefundJob::dispatch($refund->id);

        return $refund;
    }
}
