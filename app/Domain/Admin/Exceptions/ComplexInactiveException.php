<?php

namespace App\Domain\Admin\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * A7 — the complex is deactivated (complexes.is_active = false): no new invitation, membership, subscription
 * or payment for it. Access already paid for keeps working until it ends → 422.
 */
final class ComplexInactiveException extends DomainException
{
    public function __construct(private readonly int $complexId)
    {
        parent::__construct('The complex is not active.');
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'complex_inactive';
    }

    public function messageKey(): string
    {
        return 'errors.complex_inactive';
    }

    public function details(): ?array
    {
        return ['complex_id' => $this->complexId];
    }

    /** Throws when the complex exists and is deactivated (an unknown complex is the caller's 404 to raise). */
    public static function assertActive(?int $complexId): void
    {
        if ($complexId !== null && \App\Domain\Admin\Models\Complex::query()->whereKey($complexId)->where('is_active', false)->exists()) {
            throw new self($complexId);
        }
    }
}
