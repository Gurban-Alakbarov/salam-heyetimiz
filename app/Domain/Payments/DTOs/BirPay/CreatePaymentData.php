<?php

namespace App\Domain\Payments\DTOs\BirPay;

/**
 * Typed input for POST /v1/payments. Amount is held in minor units (our canonical) and emitted as the API's
 * decimal `amount.value`.
 */
final readonly class CreatePaymentData
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{merchantId: string, terminalId: string}|null  $posDetail  required for POS-terminal merchants
     */
    public function __construct(
        public int $amountMinor,
        public string $currency,
        public bool $capture,
        public ?string $description,
        public string $paymentMethodType,   // '' = omit (hosted page shows all methods); else M10 | BIRBANK
        public string $confirmationType,    // REDIRECT | QR | MOBILE
        public string $returnUrl,
        public array $metadata = [],
        public ?array $posDetail = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        $body = [
            'amount' => [
                'value' => round($this->amountMinor / 100, 2),
                'currency' => $this->currency,
            ],
            'capture' => $this->capture,
            'confirmation' => array_filter([
                'type' => $this->confirmationType,
                'returnUrl' => $this->returnUrl !== '' ? $this->returnUrl : null,
            ], static fn ($v) => $v !== null),
        ];
        // For a REDIRECT hosted checkout page, paymentMethodData is omitted so the page offers all methods
        // (card incl.). Pinning BANK_CARD with REDIRECT is rejected (invalid_operation) by the gateway.
        if ($this->paymentMethodType !== '') {
            $body['paymentMethodData'] = ['type' => $this->paymentMethodType];
        }
        if ($this->description !== null && $this->description !== '') {
            $body['description'] = mb_substr($this->description, 0, 255);
        }
        if ($this->metadata !== []) {
            $body['metadata'] = $this->metadata;
        }
        if ($this->posDetail !== null && ($this->posDetail['merchantId'] ?? '') !== '' && ($this->posDetail['terminalId'] ?? '') !== '') {
            $body['posDetail'] = ['merchantId' => $this->posDetail['merchantId'], 'terminalId' => $this->posDetail['terminalId']];
        }

        return $body;
    }
}
