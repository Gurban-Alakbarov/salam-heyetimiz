<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A previously-disabled device was re-enabled by an admin. */
class DeviceEnabled implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Device $device) {}

    public function auditAction(): string
    {
        return 'device.enabled';
    }

    public function auditPayload(): array
    {
        return ['device_id' => $this->device->getKey()];
    }
}
