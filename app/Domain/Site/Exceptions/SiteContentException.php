<?php

namespace App\Domain\Site\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Two admins edited the same page: saving would silently undo the other's work. */
final class SiteContentException extends DomainException
{
    public function errorCode(): string
    {
        return 'site_content_conflict';
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function messageKey(): string
    {
        return 'errors.site_content_conflict';
    }
}
