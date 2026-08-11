<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PaymentLogDirection;
use App\Domain\Payments\Models\PaymentLog;
use App\Support\Time\Clock;

/**
 * Writes payment_logs with **allowlist** serialization + app-layer encryption (HIGH-15 / R-PAY-10).
 * Only allowlisted fields are persisted; everything else is dropped before encryption, so a new
 * bank field can never silently leak PAN/CVV into the log. The model's `encrypted` cast encrypts
 * the allowlisted JSON at rest. A daily PaymentLogsScannerJob defends in depth.
 */
final class PaymentLogger
{
    /** Fields permitted to persist. Everything else is stripped (no PAN/CVV can leak). */
    public const ALLOWLIST = [
        // legacy + generic
        'orderId', 'id', 'reference', 'status', 'amount', 'currency',
        'rrn', 'approvalCode', 'pan', 'cardBrand', 'redirectUrl', 'paymentUrl',
        'transactionId', 'bankTransactionId', 'message', 'code',
        // BirPay V1.3 payment/refund object (none carry PAN/CVV)
        'type', 'paid', 'captured', 'settled', 'refunded', 'description', 'createdAt', 'expiresAt',
        'confirmation', 'confirmUrl', 'confirmData', 'returnUrl', 'paymentMethod', 'paymentMethodData',
        'authorizationDetail', 'threeDsSecure', 'cancelationReason', 'cancellationReason', 'cancelationParty',
        'originalId', 'merchant', 'externalId', 'mcc', 'posDetail', 'metadata', 'errors', 'expires_in',
    ];

    public function __construct(private readonly Clock $clock) {}

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    public function logOutbound(
        ?int $orderId,
        string $method,
        string $endpoint,
        ?int $httpStatus,
        ?array $request,
        ?array $response,
        ?int $latencyMs,
    ): PaymentLog {
        return PaymentLog::query()->create([
            'partition_key' => $this->partitionKey(),
            'order_id' => $orderId,
            'direction' => PaymentLogDirection::Outbound,
            'endpoint' => $endpoint,
            'http_method' => $method,
            'http_status' => $httpStatus,
            'request_redacted_encrypted' => $this->serialize($request),
            'response_redacted_encrypted' => $this->serialize($response),
            'latency_ms' => $latencyMs,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function logInbound(
        ?int $orderId,
        string $endpoint,
        ?array $payload,
        ?bool $signatureProvided,
        ?bool $signatureValid,
        ?string $ip,
        ?int $httpStatus,
    ): PaymentLog {
        return PaymentLog::query()->create([
            'partition_key' => $this->partitionKey(),
            'order_id' => $orderId,
            'direction' => PaymentLogDirection::Inbound,
            'endpoint' => $endpoint,
            'http_method' => 'POST',
            'http_status' => $httpStatus,
            'request_redacted_encrypted' => $this->serialize($payload),
            'response_redacted_encrypted' => null,
            'signature_provided' => $signatureProvided,
            'signature_valid' => $signatureValid,
            'ip' => $ip,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public static function allowlist(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        return array_intersect_key($data, array_flip(self::ALLOWLIST));
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function serialize(?array $data): ?string
    {
        if ($data === null) {
            return null;
        }

        return json_encode(self::allowlist($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }

    private function partitionKey(): int
    {
        return (int) $this->clock->now()->format('Ym');
    }
}
