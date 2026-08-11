<?php

namespace App\Domain\Devices\DTOs;

use Spatie\LaravelData\Data;

/** openapi techAssignDevice body — bind an owner (by phone) and optional placement to a device. */
class DeviceAssignmentData extends Data
{
    public function __construct(
        public readonly string $ownerPhone,
        public readonly ?int $regionId = null,
        public readonly ?string $locationLabel = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {}
}
