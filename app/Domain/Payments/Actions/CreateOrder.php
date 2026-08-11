<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\DTOs\OrderCreationData;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\OrderService;
use App\Domain\Users\Models\User;

final class CreateOrder
{
    public function __construct(private readonly OrderService $orders) {}

    public function handle(User $payer, OrderCreationData $data, ?string $idempotencyKey): Order
    {
        return $this->orders->create($payer, $data, $idempotencyKey);
    }
}
