<?php

namespace App\Domain\Applications\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A8 — an application (legal entity or individual) was rejected; the applicant is told in the app as well. */
final class ApplicationRejected
{
    use Dispatchable;

    public function __construct(
        public readonly string $kind,
        public readonly int $applicationId,
        public readonly int $userId,
        public readonly string $reason,
    ) {}
}
