<?php

namespace App\Domain\Payments\Support;

use App\Domain\Admin\Services\SettingsService;

/**
 * The single typed accessor for every payment value — all reads go through SettingsService (DB, encrypted
 * secrets, cached, audited). No payment service may read config('integrations.kapital.*') or
 * config('domain.payments.*') at runtime; they read here instead (SETTINGS_MAPPING.md).
 */
final class PaymentSettings
{
    /** Documented BirPay API hosts by mode (the only place these URLs live). */
    private const HOST = [
        'sandbox' => 'https://preapi.birpay.az',
        'production' => 'https://api.birpay.az',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /** The documented BirPay API host for a mode (the single place these URLs are defined). */
    public static function hostForMode(string $mode): string
    {
        return self::HOST[$mode === 'production' ? 'production' : 'sandbox'];
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->value('payments', 'kapital_enabled');
    }

    public function mode(): string
    {
        $mode = (string) $this->settings->value('payments', 'kapital_mode');

        return $mode === 'production' ? 'production' : 'sandbox';
    }

    /** API base URL: the configured value, or the documented host for the mode. */
    public function baseUrl(): string
    {
        $url = trim((string) $this->settings->value('payments', 'kapital_api_base_url'));

        return rtrim($url !== '' ? $url : self::HOST[$this->mode()], '/');
    }

    public function clientId(): string
    {
        return (string) $this->settings->value('payments', 'kapital_client_id');
    }

    public function clientSecret(): string
    {
        return (string) $this->settings->value('payments', 'kapital_client_secret');
    }

    public function scope(): string
    {
        $scope = trim((string) $this->settings->value('payments', 'kapital_scope'));

        return $scope !== '' ? $scope : 'email';
    }

    public function merchantId(): string
    {
        return (string) $this->settings->value('payments', 'kapital_merchant_id');
    }

    public function terminalId(): string
    {
        return (string) $this->settings->value('payments', 'kapital_terminal_id');
    }

    public function merchantName(): string
    {
        return (string) $this->settings->value('payments', 'kapital_merchant_name');
    }

    /** posDetail block (required by POS-terminal merchants) when both ids are configured. @return array{merchantId: string, terminalId: string}|null */
    public function posDetail(): ?array
    {
        $merchant = $this->merchantId();
        $terminal = $this->terminalId();

        return $merchant !== '' && $terminal !== '' ? ['merchantId' => $merchant, 'terminalId' => $terminal] : null;
    }

    public function webhookSecret(): string
    {
        return (string) $this->settings->value('payments', 'kapital_webhook_secret');
    }

    /** @return array<int, string> */
    public function ipAllowlist(): array
    {
        $raw = (string) $this->settings->value('payments', 'kapital_ip_allowlist');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function returnUrl(): string
    {
        $url = trim((string) $this->settings->value('payments', 'kapital_return_url'));

        return $url !== '' ? $url : url('/v1/payments/return');
    }

    public function webhookUrl(): string
    {
        return url('/v1/payments/webhook');
    }

    public function currency(): string
    {
        $cur = strtoupper(trim((string) $this->settings->value('payments', 'kapital_currency')));

        return $cur !== '' ? $cur : 'AZN';
    }

    public function timeoutSeconds(): int
    {
        return max(1, (int) $this->settings->value('payments', 'kapital_timeout_seconds'));
    }

    public function connectTimeoutSeconds(): int
    {
        return max(1, (int) $this->settings->value('payments', 'kapital_connect_timeout_seconds'));
    }

    public function retries(): int
    {
        return max(0, (int) $this->settings->value('payments', 'kapital_retries'));
    }

    public function callbackTimeoutMinutes(): int
    {
        return max(1, (int) $this->settings->value('payments', 'kapital_callback_timeout_minutes'));
    }

    public function authorisingRecheckMinutes(): int
    {
        return max(1, (int) $this->settings->value('payments', 'kapital_authorising_recheck_minutes'));
    }

    public function refundWindowDays(): int
    {
        return max(1, (int) $this->settings->value('payments', 'kapital_refund_window_days'));
    }

    public function loggingEnabled(): bool
    {
        return (bool) $this->settings->value('payments', 'enable_logging');
    }

    public function tokenUrl(): string
    {
        return $this->baseUrl().'/api/oauth2/token';
    }
}
