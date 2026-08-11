<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\DTOs\KapitalCallbackData;
use App\Domain\Payments\Enums\PaymentCallbackOutcome;
use App\Domain\Payments\Events\PaymentCallbackProcessed;
use App\Domain\Payments\Events\PaymentCallbackReceived;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\PaymentCallback;
use App\Support\Time\Clock;
use Illuminate\Database\QueryException;

/**
 * Persists signature-verified callbacks with durable de-duplication (R-PAY-06). Reached only after
 * the signature middleware has validated the HMAC, so every row here is authentic. PENDING handling
 * and the getOrderStatus apply are decided downstream (listener → job), never from the callback body.
 */
final class PaymentCallbackService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    public function receive(KapitalCallbackData $data, string $rawBody, ?string $ip): PaymentCallback
    {
        $payloadHash = hash('sha256', $rawBody);

        // Primary dedup: identical body already processed → idempotent replay.
        $existing = PaymentCallback::query()->where('payload_hash', $payloadHash)->first();
        if ($existing !== null) {
            return $existing;
        }

        $order = Order::query()->where('bank_order_id', $data->orderId)->first();
        $outcome = $order === null ? PaymentCallbackOutcome::Unmatched : PaymentCallbackOutcome::Applied;

        try {
            $callback = PaymentCallback::query()->create([
                'bank_order_id' => $data->orderId,
                'bank_status' => $data->status,
                'payload_hash' => $payloadHash,
                'order_id' => $order?->id,
                'outcome' => $outcome,
            ]);
        } catch (QueryException $e) {
            // Secondary unique (bank_order_id, bank_status) collision = same logical event, different bytes.
            $duplicate = PaymentCallback::query()
                ->where('bank_order_id', $data->orderId)
                ->where('bank_status', $data->status)
                ->first();

            if ($duplicate !== null) {
                return $duplicate;
            }

            throw $e;
        }

        $this->logger->logInbound(
            orderId: $order?->id,
            endpoint: '/v1/payments/webhook',
            payload: $this->loggablePayload($data),
            signatureProvided: true,
            signatureValid: true,
            ip: $ip,
            httpStatus: 200,
        );

        PaymentCallbackReceived::dispatch($callback);

        return $callback;
    }

    public function markProcessed(PaymentCallback $callback, PaymentCallbackOutcome $outcome): void
    {
        $callback->forceFill([
            'outcome' => $outcome->value,
            'processed_at' => $this->clock->now(),
        ])->save();

        PaymentCallbackProcessed::dispatch($callback);
    }

    /**
     * @return array<string, mixed>
     */
    private function loggablePayload(KapitalCallbackData $data): array
    {
        return [
            'orderId' => $data->orderId,
            'status' => $data->status,
            'amount' => $data->amount,
            'currency' => $data->currency,
            'rrn' => $data->rrn,
            'approvalCode' => $data->approvalCode,
            'pan' => $data->pan, // masked by the bank; allowlisted + encrypted at rest
            'cardBrand' => $data->cardBrand,
        ];
    }
}
