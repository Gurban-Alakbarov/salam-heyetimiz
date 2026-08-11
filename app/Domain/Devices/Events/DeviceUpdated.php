<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A device's mutable fields were updated by an admin (audited change set). */
class DeviceUpdated implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly Device $device,
        public readonly array $changes,
    ) {}

    public function auditAction(): string
    {
        return 'device.updated';
    }

    public function auditPayload(): array
    {
        return ['device_id' => $this->device->getKey(), 'changes' => array_keys($this->changes)];
    }
}
