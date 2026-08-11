<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A device was disabled by an admin — all opens blocked, history kept (R-DOM-05). */
class DeviceDisabled implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): string
    {
        return 'device.disabled';
    }

    public function auditPayload(): array
    {
        return ['device_id' => $this->device->getKey(), 'reason' => $this->reason];
    }
}
