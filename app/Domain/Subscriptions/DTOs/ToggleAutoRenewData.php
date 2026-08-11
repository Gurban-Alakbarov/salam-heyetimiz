<?php

namespace App\Domain\Subscriptions\DTOs;

use Spatie\LaravelData\Data;

class ToggleAutoRenewData extends Data
{
    public function __construct(
        public readonly bool $autoRenew,
        public readonly ?int $cardTokenId = null,
    ) {}
}
