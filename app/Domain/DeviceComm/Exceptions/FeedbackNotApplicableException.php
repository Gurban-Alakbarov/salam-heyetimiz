<?php

namespace App\Domain\DeviceComm\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** A6 — actuation feedback is only taken for a command that reached the barrier (dispatched / opened) → 409. */
final class FeedbackNotApplicableException extends DomainException
{
    public function __construct(private readonly string $state)
    {
        parent::__construct('Feedback is not applicable to this command.');
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'feedback_not_applicable';
    }

    public function messageKey(): string
    {
        return 'errors.feedback_not_applicable';
    }

    public function details(): ?array
    {
        return ['state' => $this->state];
    }
}
