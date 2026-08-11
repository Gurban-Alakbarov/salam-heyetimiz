<?php

namespace App\Domain\Payments\DTOs;

use App\Domain\Payments\Enums\BankStatus;

/** Authoritative result of PaymentGateway::getOrderStatus (R-PAY-04). */
final readonly class GatewayStatusResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public BankStatus $status,
        public ?int $amountMinor = null,
        public ?string $bankTransactionId = null,
        public ?string $panMasked = null,
        public ?string $cardBrand = null,
        public ?string $rrn = null,
        public ?string $approvalCode = null,
        public array $raw = [],
    ) {}
}
