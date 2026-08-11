<?php

namespace App\Domain\Payments\DTOs;

/**
 * Parsed Kapital callback body (openapi KapitalCallback). The callback is a HINT only;
 * the authoritative state comes from getOrderStatus (R-PAY-04).
 */
final readonly class KapitalCallbackData
{
    /**
     * @param  array<string, mixed>  $extra  any additional bank fields (additionalProperties: true)
     */
    public function __construct(
        public string $orderId,
        public string $status,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $rrn = null,
        public ?string $approvalCode = null,
        public ?string $pan = null,
        public ?string $cardBrand = null,
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $known = ['orderId', 'status', 'amount', 'currency', 'rrn', 'approvalCode', 'pan', 'cardBrand'];

        return new self(
            orderId: (string) ($payload['orderId'] ?? ''),
            status: (string) ($payload['status'] ?? ''),
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            rrn: isset($payload['rrn']) ? (string) $payload['rrn'] : null,
            approvalCode: isset($payload['approvalCode']) ? (string) $payload['approvalCode'] : null,
            pan: isset($payload['pan']) ? (string) $payload['pan'] : null,
            cardBrand: isset($payload['cardBrand']) ? (string) $payload['cardBrand'] : null,
            extra: array_diff_key($payload, array_flip($known)),
        );
    }
}
