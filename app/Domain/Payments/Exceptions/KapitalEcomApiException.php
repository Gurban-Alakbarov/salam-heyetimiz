<?php

namespace App\Domain\Payments\Exceptions;

/**
 * A Kapital e-commerce API call failed. It is a provider failure for every PaymentGateway caller (the order stays
 * as it is, the reconciler tries again), and keeps the bank's exact `errorCode` + description for the admin.
 */
class KapitalEcomApiException extends PaymentProviderUnavailableException
{
    public function __construct(
        private readonly string $bankCode,
        string $bankMessage,
        private readonly int $bankStatus,
    ) {
        parent::__construct($bankMessage !== '' ? $bankMessage : 'Kapital error: '.$bankCode);
    }

    public function bankCode(): string
    {
        return $this->bankCode;
    }

    public function bankStatus(): int
    {
        return $this->bankStatus;
    }

    /** The bank answered with its own error, so the operation did not happen. False = the outcome is unknown. */
    public function isDefinite(): bool
    {
        return $this->bankCode !== 'connection_error' && ! str_starts_with($this->bankCode, 'http_');
    }
}
