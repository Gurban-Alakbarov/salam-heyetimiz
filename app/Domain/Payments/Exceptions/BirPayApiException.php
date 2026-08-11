<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * A BirPay API call returned an error envelope `{code, message, status, errors[]}`. The exact upstream `code`
 * and `message` are preserved so the admin/UI can show the real reason (never "Unknown error").
 */
class BirPayApiException extends DomainException
{
    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    public function __construct(
        private readonly string $bankCode,
        string $bankMessage,
        private readonly int $bankStatus,
        private readonly array $errors = [],
    ) {
        parent::__construct($bankMessage !== '' ? $bankMessage : 'BirPay error: '.$bankCode);
    }

    public function bankCode(): string
    {
        return $this->bankCode;
    }

    public function bankStatus(): int
    {
        return $this->bankStatus;
    }

    /** @return array<int, array<string, mixed>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Transient bank/network errors (5xx) are retryable with the same idempotency key. */
    public function isRetryable(): bool
    {
        return $this->bankStatus >= 500;
    }

    public function httpStatus(): int
    {
        return $this->bankStatus >= 500 ? 502 : 422;
    }

    public function errorCode(): string
    {
        return 'payment_provider_error';
    }

    public function messageKey(): string
    {
        return 'errors.payment_provider_error';
    }
}
