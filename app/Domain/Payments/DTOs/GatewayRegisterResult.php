<?php

namespace App\Domain\Payments\DTOs;

/** Result of PaymentGateway::registerOrder — the bank order id + hosted-page redirect URL. */
final readonly class GatewayRegisterResult
{
    /**
     * @param  array<string, mixed>  $raw  allowlisted, redaction-safe response fields
     */
    public function __construct(
        public string $bankOrderId,
        public string $redirectUrl,
        public array $raw = [],
    ) {}
}
