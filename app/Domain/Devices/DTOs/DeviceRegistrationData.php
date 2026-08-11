<?php

namespace App\Domain\Devices\DTOs;

use App\Domain\Devices\Enums\DriverType;
use Spatie\LaravelData\Data;

/** openapi components/schemas/DeviceTechRegister — a new physical device in `unassigned` state. */
class DeviceRegistrationData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $serial,
        public readonly int $deviceModelId,
        public readonly string $simPhone,
        public readonly DriverType $driverType,
        public readonly ?int $simOperatorId = null,
        public readonly ?string $simIccid = null,
        public readonly ?string $firmwareVersion = null,
        public readonly ?int $regionId = null,
        public readonly ?string $locationLabel = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $address = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?array $metadata = null,
    ) {}
}
