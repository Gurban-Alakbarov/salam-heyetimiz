<?php

namespace App\Domain\DeviceComm\DTOs;

/**
 * A device as it exists in Traccar (read back over the REST API). Used by the reconciliation flow to
 * confirm a device is already present in Traccar — and to capture its Traccar numeric id — before we
 * adopt it into the platform. We never create a Traccar device from this DTO.
 */
final class TraccarRemoteDevice
{
    public function __construct(
        public readonly int $id,
        public readonly string $uniqueId,
        public readonly string $name,
        public readonly ?string $status = null,
    ) {}
}
