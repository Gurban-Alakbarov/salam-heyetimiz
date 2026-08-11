<?php

namespace App\Domain\Auth\DTOs;

use App\Domain\Auth\Enums\Platform;
use Spatie\LaravelData\Data;

/** openapi components/schemas/DeviceFingerprintInput — the per-install identity sent at login. */
class DeviceFingerprintData extends Data
{
    public function __construct(
        public readonly string $installUuid,
        public readonly Platform $platform,
        public readonly ?string $osVersion = null,
        public readonly ?string $appVersion = null,
        public readonly ?string $deviceModel = null,
        public readonly ?string $pushToken = null,
    ) {}
}
