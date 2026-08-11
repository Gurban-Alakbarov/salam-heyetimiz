<?php

namespace App\Domain\Payments\DTOs\BirPay;

use App\Domain\Payments\Services\PaymentLogger;

/** Parsed BirPay payment object (response of create / get payment). */
final readonly class BirPayPayment
{
    /**
     * @param  array<string, mixed>  $raw  allowlisted, redaction-safe fields
     */
    public function __construct(
        public string $id,
        public string $status,            // pending | succeeded | canceled | waiting_for_capture
        public ?string $confirmUrl,
        public ?string $confirmData,
        public ?string $confirmationType,
        public ?int $amountMinor,
        public ?string $rrn,              // authorizationDetail.rrn (card RRN) when present
        public ?string $approvalCode,
        public ?bool $threeDs,
        public ?string $cancelationReason,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromArray(array $body): self
    {
        $confirmation = is_array($body['confirmation'] ?? null) ? $body['confirmation'] : [];
        $auth = is_array($body['authorizationDetail'] ?? null) ? $body['authorizationDetail'] : [];
        $amount = is_array($body['amount'] ?? null) ? $body['amount'] : [];

        $amountMinor = isset($amount['value']) && is_numeric($amount['value'])
            ? (int) round(((float) $amount['value']) * 100)
            : null;

        return new self(
            id: (string) ($body['id'] ?? ''),
            status: strtolower((string) ($body['status'] ?? '')),
            confirmUrl: isset($confirmation['confirmUrl']) ? (string) $confirmation['confirmUrl'] : null,
            confirmData: isset($confirmation['confirmData']) ? (string) $confirmation['confirmData'] : null,
            confirmationType: isset($confirmation['type']) ? strtolower((string) $confirmation['type']) : null,
            amountMinor: $amountMinor,
            rrn: isset($auth['rrn']) ? (string) $auth['rrn'] : null,
            approvalCode: isset($auth['approvalCode']) ? (string) $auth['approvalCode'] : null,
            threeDs: isset($auth['threeDsSecure']) ? (bool) $auth['threeDsSecure'] : (isset($auth['threeDSecure']) ? (bool) $auth['threeDSecure'] : null),
            cancelationReason: $body['cancelationReason'] ?? $body['cancellationReason'] ?? null,
            raw: PaymentLogger::allowlist($body),
        );
    }
}
