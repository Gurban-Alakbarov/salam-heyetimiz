<?php

namespace App\Domain\Payments\DTOs;

use App\Domain\Payments\Enums\OrderPurpose;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class OrderCreationData extends Data
{
    /**
     * @param  array<int, OrderItemData>  $items
     */
    public function __construct(
        public readonly OrderPurpose $purpose,
        #[DataCollectionOf(OrderItemData::class)]
        public readonly array $items,
        public readonly bool $autoRenew = false,
        public readonly ?string $returnUrl = null,
    ) {}
}
