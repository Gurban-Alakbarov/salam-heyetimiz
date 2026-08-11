<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * Payment settings failed validation on save (format check or a live OAuth probe). Carries the exact reason so
 * the admin sees what is wrong and the values are NOT persisted.
 */
class PaymentSettingsInvalidException extends DomainException
{
    /**
     * @param  array<string, string>  $fields  field → message
     */
    public function __construct(string $message, private readonly array $fields = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'payment_settings_invalid';
    }

    public function messageKey(): string
    {
        return 'errors.payment_settings_invalid';
    }
}
