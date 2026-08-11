<?php

namespace App\Domain\Payments\DTOs;

use App\Domain\Payments\Enums\OrderItemType;
use Spatie\LaravelData\Data;

class OrderItemData extends Data
{
    public function __construct(
        public readonly OrderItemType $itemType,
        public readonly ?int $referencedId,
        public readonly int $quantity,
    ) {}
}
