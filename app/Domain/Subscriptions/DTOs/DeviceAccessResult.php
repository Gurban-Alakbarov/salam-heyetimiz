<?php

namespace App\Domain\Subscriptions\DTOs;

use App\Domain\Subscriptions\Enums\SuspensionReason;

/** Result of the open-permission / eligibility check (Tech Spec §13.9 / CRIT-02). */
final readonly class DeviceAccessResult
{
    public function __construct(
        public bool $canOpen,
        public SuspensionReason $suspensionReason,
    ) {}
}
