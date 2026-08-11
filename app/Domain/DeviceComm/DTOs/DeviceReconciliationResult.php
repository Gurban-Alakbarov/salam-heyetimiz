<?php

namespace App\Domain\DeviceComm\DTOs;

use App\Domain\DeviceComm\Models\TraccarDevice;
use App\Domain\Devices\Models\Device;

/**
 * Outcome of a Traccar→Laravel reconciliation. `created` distinguishes a fresh adoption (new Laravel
 * device + mapping) from an idempotent no-op (the uniqueId was already mapped).
 */
final class DeviceReconciliationResult
{
    public function __construct(
        public readonly Device $device,
        public readonly TraccarDevice $mapping,
        public readonly bool $created,
    ) {}
}
