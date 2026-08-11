<?php

namespace App\Domain\Payments\Adapters\BirPay;

use App\Domain\Payments\DTOs\BirPay\BirPayPayment;
use App\Domain\Payments\DTOs\BirPay\CreatePaymentData;
use App\Domain\Payments\Exceptions\BirPayApiException;
use App\Domain\Payments\Services\PaymentLogger;
use App\Domain\Payments\Support\PaymentSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * BirPay Merchant API HTTP client (Phase 2 scope: create + retrieve payment). Bearer auth via
 * BirPayTokenService; `X-Idempotency-Key` on POST; bounded retry on 5xx with the same key; one re-auth on 401;
 * error-envelope mapping to BirPayApiException; every call recorded via PaymentLogger (allowlisted, encrypted,
 * never the token/secret). All config from PaymentSettings.
 */
final class BirPayClient
{
    public function __construct(
        private readonly PaymentSettings $settings,
        private readonly BirPayTokenService $token,
        private readonly PaymentLogger $logger,
    ) {}

    public function createPayment(CreatePaymentData $data, string $idempotencyKey, ?int $orderId = null): BirPayPayment
    {
        $json = $this->request('POST', '/v1/payments', $data->toBody(), $idempotencyKey, $orderId);
        $payment = BirPayPayment::fromArray($json);

        if ($payment->id === '') {
            throw new BirPayApiException('malformed_response', 'BirPay create payment returned no id.', 502);
        }

        return $payment;
    }

    public function getPayment(string $id, ?int $orderId = null): BirPayPayment
    {
        return BirPayPayment::fromArray($this->request('GET', '/v1/payments/'.rawurlencode($id), null, null, $orderId));
    }

    /**
     * Create a refund against the original payment. `amountMinor` null = full refund. Returns the raw refund
     * object ({id, originalId, status, ...}).
     *
     * @return array<string, mixed>
     */
    public function createRefund(string $paymentId, ?int $amountMinor, ?string $description, string $idempotencyKey, ?int $orderId = null): array
    {
        $body = ['id' => $paymentId];
        if ($amountMinor !== null) {
            $body['amount'] = round($amountMinor / 100, 2);
        }
        if ($description !== null && $description !== '') {
            $body['description'] = mb_substr($description, 0, 255);
        }

        return $this->request('POST', '/v1/refunds', $body, $idempotencyKey, $orderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRefund(string $refundId, ?int $orderId = null): array
    {
        return $this->request('GET', '/v1/refunds/'.rawurlencode($refundId), null, null, $orderId);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body, ?string $idempotencyKey, ?int $orderId): array
    {
        $url = $this->settings->baseUrl().$path;
        $token = $this->token->token();
        $maxRetries = $this->settings->retries();
        $reauthed = false;
        $attempt = 0;

        while (true) {
            $attempt++;
            $startedAt = microtime(true);

            try {
                // A fresh PendingRequest each attempt (avoids body-stream reuse on retry).
                $req = Http::timeout($this->settings->timeoutSeconds())
                    ->connectTimeout($this->settings->connectTimeoutSeconds())
                    ->withToken($token)
                    ->acceptJson();
                if ($idempotencyKey !== null) {
                    $req = $req->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
                }
                $response = match ($method) {
                    'GET' => $req->get($url),
                    'PUT' => $req->asJson()->put($url, $body ?? []),
                    default => $req->asJson()->post($url, $body ?? []),
                };
            } catch (ConnectionException $e) {
                $this->logger->logOutbound($orderId, $method, $url, null, $body, null, $this->ms($startedAt));
                if ($attempt <= $maxRetries) {
                    $this->backoff($attempt);

                    continue;
                }

                throw new BirPayApiException('connection_error', 'BirPay unreachable: '.$e->getMessage(), 503);
            }

            $json = is_array($response->json()) ? $response->json() : [];

            // re-authenticate once on 401, without consuming a retry slot
            if ($response->status() === 401 && ! $reauthed) {
                $reauthed = true;
                $token = $this->token->forceRefresh();

                continue;
            }

            $this->logger->logOutbound($orderId, $method, $url, $response->status(), $body, $json, $this->ms($startedAt));

            // BirPay may return an error envelope ({code, status>=400, ...}) even with HTTP 200 — treat as error.
            $envelopeError = isset($json['code'], $json['status']) && (int) $json['status'] >= 400;

            // retry transient 5xx with the same idempotency key
            if (($response->serverError() || ($envelopeError && (int) $json['status'] >= 500)) && $attempt <= $maxRetries) {
                $this->backoff($attempt);

                continue;
            }

            if ($response->failed() || $envelopeError) {
                $status = $envelopeError ? (int) $json['status'] : $response->status();
                throw new BirPayApiException(
                    (string) ($json['code'] ?? 'http_'.$status),
                    (string) ($json['message'] ?? 'BirPay error'),
                    $status,
                    is_array($json['errors'] ?? null) ? $json['errors'] : [],
                );
            }

            return $json;
        }
    }

    private function backoff(int $attempt): void
    {
        if (! app()->environment('testing')) {
            usleep(min(2_000_000, 200_000 * $attempt));
        }
    }

    private function ms(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
