<?php

namespace App\Domain\Subscriptions\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Subscribe called while the caller's subscription on that device is already active → 409 (renew instead). */
class SubscriptionAlreadyActiveException extends DomainException
{
    public function __construct(private readonly int $subscriptionId)
    {
        parent::__construct('Bu cihaz üçün aktiv abunəliyiniz var; yeniləmək üçün renew istifadə edin.');
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'subscription_already_active';
    }

    public function messageKey(): string
    {
        return 'errors.subscription_already_active';
    }

    public function details(): ?array
    {
        return ['subscription_id' => $this->subscriptionId];
    }
}
