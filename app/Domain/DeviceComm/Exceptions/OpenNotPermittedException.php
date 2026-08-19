<?php

namespace App\Domain\DeviceComm\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * The caller may not open this device right now (R-DOM-05). 403 with the per-caller reason mapped to
 * the standard codes (openapi openDevice 403: subscription_required / device_disabled / forbidden).
 */
class OpenNotPermittedException extends DomainException
{
    /**
     * @param  array<string, mixed>|null  $detail
     */
    private function __construct(private readonly string $errorCode, private readonly ?array $detail = null)
    {
        parent::__construct('Open not permitted.');
    }

    public static function subscriptionRequired(int $deviceId): self
    {
        return new self('subscription_required', ['device_id' => $deviceId]);
    }

    public static function deviceDisabled(int $deviceId): self
    {
        return new self('device_disabled', ['device_id' => $deviceId]);
    }

    public static function forbidden(): self
    {
        return new self('forbidden');
    }

    /** Geofence is enabled for the device but the caller sent no location (GEOFENCE-1). */
    public static function locationRequired(int $deviceId): self
    {
        return new self('location_required', ['device_id' => $deviceId]);
    }

    /** The caller's location is outside the device's configured radius (accuracy margin applied). */
    public static function outsideGeofence(int $deviceId): self
    {
        return new self('outside_geofence', ['device_id' => $deviceId]);
    }

    /** The caller's GPS accuracy is too poor to make a reliable in-zone decision (D4). */
    public static function locationImprecise(int $deviceId): self
    {
        return new self('location_imprecise', ['device_id' => $deviceId]);
    }

    public function httpStatus(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function messageKey(): string
    {
        return "errors.{$this->errorCode}";
    }

    public function details(): ?array
    {
        return $this->detail;
    }
}
