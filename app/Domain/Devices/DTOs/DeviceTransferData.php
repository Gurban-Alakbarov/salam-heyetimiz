<?php

namespace App\Domain\Devices\DTOs;

use Spatie\LaravelData\Data;

/** openapi adminTransferDevice body — re-owner a device to another phone (admin-mediated, R-DOM-12). */
class DeviceTransferData extends Data
{
    public function __construct(
        public readonly string $newOwnerPhone,
        public readonly string $reason,
        public readonly bool $keepExistingUsers = true,
    ) {}
}
