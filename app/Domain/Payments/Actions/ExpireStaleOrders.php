<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Services\OrderService;

final class ExpireStaleOrders
{
    public function __construct(private readonly OrderService $orders) {}

    public function handle(): int
    {
        return $this->orders->expireStale();
    }
}
