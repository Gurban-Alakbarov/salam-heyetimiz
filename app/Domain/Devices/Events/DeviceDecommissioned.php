<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A device was decommissioned (terminal, soft-deleted). Its roster is revoked asynchronously. */
class DeviceDecommissioned implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $deviceId,
        public readonly string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'device.decommissioned';
    }

    public function auditPayload(): array
    {
        return ['device_id' => $this->deviceId, 'reason' => $this->reason];
    }
}
