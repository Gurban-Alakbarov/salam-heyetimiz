<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Contracts\DeviceDriver;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the effective driver for a device (R-GSM-02, v1.2). The driver *type* is the device's own
 * `driver_type` (the per-operator caller-ID fallback policy is retired with CLIP — CRIT-01). The driver
 * *instance* is looked up from the container under `device-driver.<type>` (TraccarDriver / SmsDriver
 * bind there in the DeviceComm module); returns null if none is registered.
 */
final class DriverResolver
{
    public function __construct(private readonly Container $container) {}

    public function driverTypeFor(Device $device): DriverType
    {
        return $device->driver_type;
    }

    public function resolve(Device $device): ?DeviceDriver
    {
        return $this->resolveType($this->driverTypeFor($device));
    }

    /** Resolve a concrete driver instance for a type from the container, or null if none registered. */
    public function resolveType(DriverType $type): ?DeviceDriver
    {
        $abstract = 'device-driver.'.$type->value;

        $driver = $this->container->bound($abstract) ? $this->container->make($abstract) : null;

        return $driver instanceof DeviceDriver ? $driver : null;
    }
}
