<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A new physical device was registered (created in `unassigned`). */
class DeviceRegistered implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Device $device) {}

    public function auditAction(): string
    {
        return 'device.registered';
    }

    public function auditPayload(): array
    {
        return [
            'device_id' => $this->device->getKey(),
            'serial' => $this->device->serial,
            'device_model_id' => $this->device->device_model_id,
        ];
    }
}
