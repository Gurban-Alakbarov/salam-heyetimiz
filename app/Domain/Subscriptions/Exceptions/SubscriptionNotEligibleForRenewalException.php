<?php

namespace App\Domain\Subscriptions\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Subscription cannot be renewed in its current state → 409 (openapi renewSubscription 409). */
class SubscriptionNotEligibleForRenewalException extends DomainException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(string $message = 'Subscription is not eligible for renewal.', private readonly ?array $detailData = null)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'subscription_not_renewable';
    }

    public function messageKey(): string
    {
        return 'errors.subscription_not_renewable';
    }

    public function details(): ?array
    {
        return $this->detailData;
    }
}
