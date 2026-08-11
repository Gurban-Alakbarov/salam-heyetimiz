<?php

namespace App\Domain\Subscriptions\DTOs;

use Spatie\LaravelData\Data;

class RenewSubscriptionData extends Data
{
    public function __construct(
        public readonly ?string $returnUrl = null,
    ) {}
}
