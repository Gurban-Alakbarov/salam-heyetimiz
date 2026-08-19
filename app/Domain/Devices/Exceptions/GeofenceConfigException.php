<?php

namespace App\Domain\Devices\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * Invalid geofence configuration (GEOFENCE-1). 422 — enabling a device geofence requires a positive
 * radius AND the device to already have coordinates. Owners cannot set coordinates (admin-only), so an
 * owner enabling a geofence on a coordinate-less device gets `geofence_device_location_missing`.
 */
class GeofenceConfigException extends DomainException
{
    private function __construct(private readonly string $errorCode)
    {
        parent::__construct('Invalid geofence configuration.');
    }

    public static function radiusRequired(): self
    {
        return new self('geofence_radius_required');
    }

    public static function deviceLocationMissing(): self
    {
        return new self('geofence_device_location_missing');
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function messageKey(): string
    {
        return "errors.{$this->errorCode}";
    }
}
