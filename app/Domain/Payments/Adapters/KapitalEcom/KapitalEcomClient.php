<?php

namespace App\Domain\Payments\Adapters\KapitalEcom;

use App\Domain\Payments\Exceptions\KapitalEcomApiException;
use App\Domain\Payments\Services\PaymentLogger;
use App\Domain\Payments\Support\PaymentSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Kapital e-commerce API HTTP client (docs/integrations/kapital/ecom/API_REFERENCE.md). Basic auth. The API has no
 * idempotency key, so only GET is retried: a POST is sent once and its outcome is settled by reading the order.
 * Every call is recorded via PaymentLogger with a safe summary only: the order `password` and `secret` the bank
 * returns are never logged. All config from PaymentSettings.
 */
final class KapitalEcomClient
{
    private const DETAIL = '?tranDetailLevel=2&tokenDetailLevel=2&orderDetailLevel=2';

    public function __construct(
        private readonly PaymentSettings $settings,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * POST /order (Order_SMS — a one-step purchase on the hosted page).
     *
     * @return array<string, mixed> {id, hppUrl, password, status}
     */
    public function createOrder(int $amountMinor, string $currency, string $title, string $description, string $language, string $redirectUrl, ?int $orderId = null): array
    {
        $json = $this->request('POST', '/order', ['order' => [
            'typeRid' => 'Order_SMS',
            'amount' => self::decimal($amountMinor),
            'currency' => $currency,
            'language' => $language,
            'title' => $title,
            'description' => mb_substr($description, 0, 255),
            'hppRedirectUrl' => $redirectUrl,
        ]], $orderId);

        $order = is_array($json['order'] ?? null) ? $json['order'] : [];
        if (($order['id'] ?? '') === '' || ($order['hppUrl'] ?? '') === '' || ($order['password'] ?? '') === '') {
            throw new KapitalEcomApiException('malformed_response', 'Kapital create order returned no hosted page.', 502);
        }

        return $order;
    }

    /**
     * GET /order/{id} with full transaction + card detail.
     *
     * @return array<string, mixed>
     */
    public function getOrder(string $id, ?int $orderId = null): array
    {
        $json = $this->request('GET', '/order/'.rawurlencode($id).self::DETAIL, null, $orderId);
        $order = is_array($json['order'] ?? null) ? $json['order'] : [];

        if (($order['status'] ?? '') === '') {
            throw new KapitalEcomApiException('malformed_response', 'Kapital order response has no status.', 502);
        }

        return $order;
    }

    /**
     * POST /order/{id}/exec-tran with type=Refund.
     *
     * @return array<string, mixed> the `tran` object
     */
    public function refund(string $id, int $amountMinor, ?int $orderId = null): array
    {
        $json = $this->request('POST', '/order/'.rawurlencode($id).'/exec-tran', ['tran' => [
            'phase' => 'Single',
            'amount' => self::decimal($amountMinor),
            'type' => 'Refund',
        ]], $orderId);

        return is_array($json['tran'] ?? null) ? $json['tran'] : [];
    }

    /**
     * Checks a login without creating anything: an unknown order is a 404 for a valid login, and an InvalidLogin
     * error otherwise.
     *
     * @return array{ok: bool, http_status: int|null, message: string}
     */
    public function probe(string $baseUrl, string $username, string $password): array
    {
        try {
            $response = Http::timeout(8)->withBasicAuth($username, $password)->acceptJson()
                ->get(rtrim($baseUrl, '/').'/order/999999999');
        } catch (ConnectionException $e) {
            return ['ok' => false, 'http_status' => null, 'message' => 'Bank əlçatan deyil: '.substr($e->getMessage(), 0, 120)];
        }

        $json = is_array($response->json()) ? $response->json() : [];
        $message = (string) ($json['errorDescription'] ?? ('HTTP '.$response->status()));

        return ['ok' => $response->status() === 404, 'http_status' => $response->status(), 'message' => $message];
    }

    /** Money as the API expects it: 1500 → "15.00". */
    public static function decimal(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }

    public static function minor(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body, ?int $orderId): array
    {
        $url = $this->settings->ecomBaseUrl().$path;
        $maxRetries = $method === 'GET' ? $this->settings->retries() : 0;
        $safeRequest = $this->summary($body['order'] ?? $body['tran'] ?? null);
        $attempt = 0;

        while (true) {
            $attempt++;
            $startedAt = microtime(true);

            try {
                $req = Http::timeout($this->settings->timeoutSeconds())
                    ->connectTimeout($this->settings->connectTimeoutSeconds())
                    ->withBasicAuth($this->settings->ecomUsername(), $this->settings->ecomPassword())
                    ->acceptJson();
                $response = $method === 'GET' ? $req->get($url) : $req->asJson()->post($url, $body ?? []);
            } catch (ConnectionException $e) {
                $this->logger->logOutbound($orderId, $method, $url, null, $safeRequest, null, $this->ms($startedAt));
                if ($attempt <= $maxRetries) {
                    $this->backoff($attempt);

                    continue;
                }

                throw new KapitalEcomApiException('connection_error', 'Kapital unreachable: '.$e->getMessage(), 503);
            }

            $json = is_array($response->json()) ? $response->json() : [];
            $this->logger->logOutbound($orderId, $method, $url, $response->status(), $safeRequest, $this->responseSummary($json), $this->ms($startedAt));

            if ($response->serverError() && $attempt <= $maxRetries) {
                $this->backoff($attempt);

                continue;
            }

            if ($response->failed() || isset($json['errorCode'])) {
                throw new KapitalEcomApiException(
                    (string) ($json['errorCode'] ?? 'http_'.$response->status()),
                    (string) ($json['errorDescription'] ?? 'Kapital error'),
                    $response->status(),
                );
            }

            return $json;
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function responseSummary(array $json): array
    {
        if (isset($json['errorCode'])) {
            return ['code' => $json['errorCode'], 'message' => $json['errorDescription'] ?? null];
        }

        return $this->summary($json['order'] ?? $json['tran'] ?? []) ?? [];
    }

    /**
     * Only these named fields leave this class — `password` and `secret` are never among them.
     *
     * @return array<string, mixed>|null
     */
    private function summary(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        return array_intersect_key($data, array_flip(['id', 'status', 'amount', 'currency', 'type', 'description', 'approvalCode']));
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
