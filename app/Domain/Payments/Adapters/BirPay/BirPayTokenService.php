<?php

namespace App\Domain\Payments\Adapters\BirPay;

use App\Domain\Payments\Exceptions\BirPayAuthException;
use App\Domain\Payments\Services\PaymentLogger;
use App\Domain\Payments\Support\PaymentSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * BirPay OAuth2 client_credentials token service (Keycloak). The access token (JWT, ~300 s, no refresh token)
 * is cached in the application cache (Redis in prod) and evicted 30 s early so it is always refreshed before
 * expiry. All credentials come from PaymentSettings. The client_secret and the bearer token are NEVER logged.
 */
final class BirPayTokenService
{
    private const CACHE_PREFIX = 'birpay:token:';

    public function __construct(
        private readonly PaymentSettings $settings,
        private readonly PaymentLogger $logger,
    ) {}

    /** A valid bearer token from cache, fetching one if absent/expired. */
    public function token(): string
    {
        $cached = Cache::get($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->refreshAndCache();
    }

    /** Force a fresh token (used after a 401). */
    public function forceRefresh(): string
    {
        Cache::forget($this->cacheKey());

        return $this->refreshAndCache();
    }

    /**
     * Raw token request with explicit credentials — used by token caching AND by settings validation /
     * test-connection (which must probe candidate credentials without touching the cache).
     *
     * @return array{access_token: string, expires_in: int, token_type: string, scope: string}
     */
    public function fetchWith(string $baseUrl, string $clientId, string $clientSecret, string $scope): array
    {
        $url = rtrim($baseUrl, '/').'/api/oauth2/token';
        $startedAt = microtime(true);

        try {
            $response = Http::asForm()
                ->timeout($this->settings->timeoutSeconds())
                ->connectTimeout($this->settings->connectTimeoutSeconds())
                ->withHeaders(['Accept' => 'application/json'])
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'scope' => $scope,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);
        } catch (ConnectionException $e) {
            // never log the secret; record only the endpoint + that it was unreachable
            $this->logger->logOutbound(null, 'POST', $url, null, ['grant_type' => 'client_credentials', 'scope' => $scope], null, $this->ms($startedAt));

            throw new BirPayAuthException('BirPay auth host unreachable: '.$e->getMessage());
        }

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        // log status + non-sensitive fields only (no token, no secret)
        $this->logger->logOutbound(
            null, 'POST', $url, $response->status(),
            ['grant_type' => 'client_credentials', 'scope' => $scope],
            ['token_type' => $json['token_type'] ?? null, 'expires_in' => $json['expires_in'] ?? null, 'scope' => $json['scope'] ?? null],
            $this->ms($startedAt),
        );

        if ($response->failed() || ! isset($json['access_token'])) {
            $desc = (string) ($json['error_description'] ?? $json['error'] ?? ('HTTP '.$response->status()));

            throw new BirPayAuthException('OAuth token request failed: '.$desc, $json['error'] ?? null);
        }

        return [
            'access_token' => (string) $json['access_token'],
            'expires_in' => (int) ($json['expires_in'] ?? 300),
            'token_type' => (string) ($json['token_type'] ?? 'Bearer'),
            'scope' => (string) ($json['scope'] ?? $scope),
        ];
    }

    private function refreshAndCache(): string
    {
        $res = $this->fetchWith($this->settings->baseUrl(), $this->settings->clientId(), $this->settings->clientSecret(), $this->settings->scope());
        $ttl = max(30, $res['expires_in'] - 30);
        Cache::put($this->cacheKey(), $res['access_token'], $ttl);

        return $res['access_token'];
    }

    private function cacheKey(): string
    {
        return self::CACHE_PREFIX.md5($this->settings->baseUrl().'|'.$this->settings->clientId());
    }

    private function ms(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
