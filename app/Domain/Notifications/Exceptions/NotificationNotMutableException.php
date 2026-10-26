<?php

namespace App\Domain\Notifications\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** O13 — this notification cannot be switched off (e.g. subscription reminders) or does not exist → 422. */
class NotificationNotMutableException extends DomainException
{
    public function __construct(private readonly string $templateKey)
    {
        parent::__construct('Notification is not user-mutable.');
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'notification_not_mutable';
    }

    public function messageKey(): string
    {
        return 'errors.notification_not_mutable';
    }

    public function details(): ?array
    {
        return ['template_key' => $this->templateKey];
    }
}
