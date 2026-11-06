<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Adapters\KapitalEcom\KapitalEcomClient;
use App\Domain\Payments\Exceptions\PaymentSettingsInvalidException;
use App\Domain\Payments\Support\PaymentSettings;

/**
 * Validates the payment settings on save: format checks + a live login check at the bank. On failure nothing is
 * persisted and the EXACT upstream reason is surfaced (never a generic "Unknown error"). Nothing is created at the
 * bank and no card is charged.
 *
 * @phpstan-type Diagnostics array{ok: bool, login: array{ok: bool, http_status: int|null, message: string}, host: string, mode: string, username: string}
 */
final class PaymentSettingsValidator
{
    private const ALLOWED_CURRENCIES = ['AZN'];

    public function __construct(private readonly KapitalEcomClient $kapital) {}

    /**
     * Validate effective settings (already merged: incoming over existing, secrets resolved).
     *
     * @param  array<string, mixed>  $s
     *
     * @throws PaymentSettingsInvalidException
     */
    public function validate(array $s): void
    {
        $fields = [];
        $username = trim((string) ($s['kapital_ecom_username'] ?? ''));
        $password = (string) ($s['kapital_ecom_password'] ?? '');
        if ($username === '') {
            $fields['kapital_ecom_username'] = 'Login tələb olunur.';
        }
        if ($password === '') {
            $fields['kapital_ecom_password'] = 'Şifrə tələb olunur.';
        }
        $currency = strtoupper(trim((string) ($s['kapital_currency'] ?? 'AZN')));
        if (! in_array($currency, self::ALLOWED_CURRENCIES, true)) {
            $fields['kapital_currency'] = 'Dəstəklənməyən valyuta: '.$currency;
        }
        $returnUrl = trim((string) ($s['kapital_return_url'] ?? ''));
        if ($returnUrl !== '' && ! $this->isUrl($returnUrl)) {
            $fields['kapital_return_url'] = 'Return URL düzgün deyil.';
        }
        if ($fields !== []) {
            throw new PaymentSettingsInvalidException('Ödəniş parametrləri düzgün deyil.', $fields);
        }

        $probe = $this->kapital->probe($this->base($s), $username, $password);
        if (! $probe['ok']) {
            throw new PaymentSettingsInvalidException('Giriş uğursuz: '.$probe['message'], [
                'kapital_ecom_password' => $probe['message'],
            ]);
        }
    }

    /**
     * Full diagnostics for the Test Connection panel. Nothing is created at the bank.
     *
     * @param  array<string, mixed>  $s  effective settings
     * @return Diagnostics
     */
    public function diagnose(array $s): array
    {
        $username = trim((string) ($s['kapital_ecom_username'] ?? ''));
        $probe = $this->kapital->probe($this->base($s), $username, (string) ($s['kapital_ecom_password'] ?? ''));

        return [
            'ok' => $probe['ok'],
            'login' => $probe,
            'host' => $this->base($s),
            'mode' => ($s['kapital_mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox',
            'username' => $username,
        ];
    }

    /**
     * @param  array<string, mixed>  $s
     */
    private function base(array $s): string
    {
        return PaymentSettings::ecomHostForMode((string) ($s['kapital_mode'] ?? 'sandbox'));
    }

    private function isUrl(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://');
    }
}
