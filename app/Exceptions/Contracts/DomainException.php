<?php

namespace App\Exceptions\Contracts;

use RuntimeException;

/**
 * Base for all domain exceptions (R-CODE-04). Concrete exceptions live in their
 * module's Exceptions/ namespace and only implement the four hooks below; the
 * ApiExceptionRenderer maps them to the standard error envelope (R-API-05), so
 * adding an error is one class — never edit the exception handler.
 */
abstract class DomainException extends RuntimeException
{
    /** HTTP status to return (e.g. 403, 409, 429). */
    abstract public function httpStatus(): int;

    /** Stable machine-readable code (e.g. subscription_required). */
    abstract public function errorCode(): string;

    /** Translation key, 1:1 with the code (e.g. errors.subscription_required) — R-LOC-05. */
    abstract public function messageKey(): string;

    /**
     * Structured detail / placeholder values for the client.
     *
     * @return array<string, mixed>|null
     */
    public function details(): ?array
    {
        return null;
    }

    /**
     * Extra response headers (e.g. Retry-After on 429). Merged by the renderer.
     *
     * @return array<string, string|int>
     */
    public function headers(): array
    {
        return [];
    }
}
