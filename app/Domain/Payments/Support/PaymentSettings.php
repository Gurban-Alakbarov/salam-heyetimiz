<?php

namespace App\Domain\Payments\Support;

use App\Domain\Admin\Services\SettingsService;

/**
 * The single typed accessor for every payment value — all reads go through SettingsService (DB, encrypted
 * secrets, cached, audited). No payment service may read config('domain.payments.*') at runtime; they read here
 * instead.
 */
final class PaymentSettings
{
    /** Kapital e-commerce API base URLs by mode (the only place these URLs live). */
    private const ECOM_HOST = [
        'sandbox' => 'https://txpgtst.kapitalbank.az/api',
        'production' => 'https://e-commerce.kapitalbank.az/api',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /** The Kapital e-commerce API base URL for a mode. */
    public static function ecomHostForMode(string $mode): string
    {
        return self::ECOM_HOST[$mode === 'production' ? 'production' : 'sandbox'];
    }

    public function ecomBaseUrl(): string
    {
        return self::ecomHostForMode($this->mode());
    }

    public function ecomUsername(): string
    {
        return trim((string) $this->settings->value('payments', 'kapital_ecom_username'));
    }

    public function ecomPassword(): string
    {
        return (string) $this->settings->value('payments', 'kapital_ecom_password');
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

    public function returnUrl(): string
    {
        $url = trim((string) $this->settings->value('payments', 'kapital_return_url'));

        return $url !== '' ? $url : url('/v1/payments/return');
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
}
