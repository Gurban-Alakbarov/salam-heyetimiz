<?php

namespace App\Domain\Payments\DTOs;

/** Result of PaymentGateway::refund. */
final readonly class GatewayRefundResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $approved,
        public ?string $bankTransactionId = null,
        public ?string $failureReason = null,
        public array $raw = [],
    ) {}
}
