<?php

namespace App\Domain\Payments\DTOs;

use Spatie\LaravelData\Data;

class RefundRequestData extends Data
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $reason,
    ) {}
}
