<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Adapters\BirPay\BirPayTokenService;
use App\Domain\Payments\Exceptions\BirPayAuthException;
use App\Domain\Payments\Exceptions\PaymentSettingsInvalidException;
use App\Domain\Payments\Support\PaymentSettings;
use Illuminate\Support\Facades\Http;

/**
 * Validates the payment settings on save: format/consistency checks + a live OAuth probe + an authenticated
 * reachability check. On failure nothing is persisted and the EXACT upstream reason is surfaced (never a
 * generic "Unknown error"). No payment is created and no card is charged.
 *
 * @phpstan-type Diagnostics array{ok: bool, oauth: array<string,mixed>, authenticated: array<string,mixed>, host: string, mode: string, merchant_id: string, terminal_id: string}
 */
final class PaymentSettingsValidator
{
    private const ALLOWED_CURRENCIES = ['AZN'];

    public function __construct(private readonly BirPayTokenService $token) {}

    /**
     * Validate effective settings (already merged: incoming over existing, secrets resolved).
     *
     * @param  array<string, mixed>  $s
     *
     * @throws PaymentSettingsInvalidException
     */
    public function validate(array $s): void
    {
        $mode = ($s['kapital_mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $base = $this->baseUrl($s, $mode);
        $clientId = trim((string) ($s['kapital_client_id'] ?? ''));
        $clientSecret = (string) ($s['kapital_client_secret'] ?? '');
        $scope = trim((string) ($s['kapital_scope'] ?? 'email')) ?: 'email';

        $fields = [];
        if (! $this->isUrl($base)) {
            $fields['kapital_api_base_url'] = 'Düzgün URL deyil.';
        }
        if ($clientId === '') {
            $fields['kapital_client_id'] = 'Client ID tələb olunur.';
        }
        if ($clientSecret === '') {
            $fields['kapital_client_secret'] = 'Client Secret tələb olunur.';
        }
        if (trim((string) ($s['kapital_merchant_id'] ?? '')) === '') {
            $fields['kapital_merchant_id'] = 'Merchant ID tələb olunur.';
        }
        if (trim((string) ($s['kapital_terminal_id'] ?? '')) === '') {
            $fields['kapital_terminal_id'] = 'Terminal ID tələb olunur.';
        }
        $currency = strtoupper(trim((string) ($s['kapital_currency'] ?? 'AZN')));
        if (! in_array($currency, self::ALLOWED_CURRENCIES, true)) {
            $fields['kapital_currency'] = 'Dəstəklənməyən valyuta: '.$currency;
        }
        $returnUrl = trim((string) ($s['kapital_return_url'] ?? ''));
        if ($returnUrl !== '' && ! $this->isUrl($returnUrl)) {
            $fields['kapital_return_url'] = 'Return URL düzgün deyil.';
        }
        // sandbox/production host consistency
        if ($this->isUrl($base)) {
            $isProdHost = str_contains($base, 'api.birpay.az') && ! str_contains($base, 'preapi');
            if ($mode === 'production' && ! $isProdHost) {
                $fields['kapital_api_base_url'] = 'Production rejimi üçün host api.birpay.az olmalıdır.';
            }
            if ($mode === 'sandbox' && $isProdHost) {
                $fields['kapital_api_base_url'] = 'Sandbox rejimi üçün preprod host (preapi.birpay.az) olmalıdır.';
            }
        }

        if ($fields !== []) {
            throw new PaymentSettingsInvalidException('Ödəniş parametrləri düzgün deyil.', $fields);
        }

        // Live OAuth probe — surfaces the exact upstream reason.
        try {
            $this->token->fetchWith($base, $clientId, $clientSecret, $scope);
        } catch (BirPayAuthException $e) {
            throw new PaymentSettingsInvalidException('OAuth uğursuz: '.$e->getMessage(), [
                'kapital_client_secret' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Full diagnostics for the Test Connection panel (OAuth + authenticated reachability). Never creates a
     * payment.
     *
     * @param  array<string, mixed>  $s  effective settings
     * @return Diagnostics
     */
    public function diagnose(array $s): array
    {
        $mode = ($s['kapital_mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $base = $this->baseUrl($s, $mode);

        $oauth = ['ok' => false, 'message' => '', 'expires_in' => null, 'token_type' => null, 'scope' => null];
        $authenticated = ['ok' => false, 'http_status' => null, 'message' => ''];

        try {
            $res = $this->token->fetchWith($base, trim((string) ($s['kapital_client_id'] ?? '')), (string) ($s['kapital_client_secret'] ?? ''), trim((string) ($s['kapital_scope'] ?? 'email')) ?: 'email');
            $oauth = ['ok' => true, 'message' => 'Token alındı.', 'expires_in' => $res['expires_in'], 'token_type' => $res['token_type'], 'scope' => $res['scope']];

            // authenticated reachability — a GET that creates nothing; expect 400 payment_not_found
            $resp = Http::withToken($res['access_token'])->timeout(8)->acceptJson()
                ->get(rtrim($base, '/').'/v1/payments/00000000-0000-0000-0000-000000000000');
            $body = is_array($resp->json()) ? $resp->json() : [];
            $code = (string) ($body['code'] ?? '');
            $authenticated = [
                'ok' => in_array($resp->status(), [400, 404], true) || $code === 'payment_not_found',
                'http_status' => $resp->status(),
                'message' => $code !== '' ? $code : ('HTTP '.$resp->status()),
            ];
        } catch (BirPayAuthException $e) {
            $oauth['message'] = $e->getMessage();
        }

        return [
            'ok' => $oauth['ok'] && $authenticated['ok'],
            'oauth' => $oauth,
            'authenticated' => $authenticated,
            'host' => $base,
            'mode' => $mode,
            'merchant_id' => (string) ($s['kapital_merchant_id'] ?? ''),
            'terminal_id' => (string) ($s['kapital_terminal_id'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $s
     */
    private function baseUrl(array $s, string $mode): string
    {
        $url = trim((string) ($s['kapital_api_base_url'] ?? ''));

        return rtrim($url !== '' ? $url : PaymentSettings::hostForMode($mode), '/');
    }

    private function isUrl(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://');
    }
}
