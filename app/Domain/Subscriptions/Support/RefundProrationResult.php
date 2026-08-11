<?php

namespace App\Domain\Subscriptions\Support;

use Carbon\CarbonImmutable;

final readonly class RefundProrationResult
{
    public function __construct(
        public bool $fullRefund,
        public int $daysToRemove,
        public CarbonImmutable $newEndsAt,
    ) {}
}
