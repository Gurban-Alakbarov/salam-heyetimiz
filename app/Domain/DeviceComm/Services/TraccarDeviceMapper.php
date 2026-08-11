<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\DeviceComm\Contracts\TraccarClient;
use App\Domain\DeviceComm\Models\TraccarDevice;
use App\Domain\Devices\Models\Device;
use App\Support\Time\Clock;

/**
 * Maps platform devices to their Traccar identity and provisions them in Traccar (v1.2). The Traccar
 * `uniqueId` is the value the UMKa reports over Wialon (its IMEI in production); registration is
 * idempotent per device.
 */
final class TraccarDeviceMapper
{
    public function __construct(
        private readonly TraccarClient $traccar,
        private readonly Clock $clock,
    ) {}

    public function forDevice(Device $device): ?TraccarDevice
    {
        return TraccarDevice::query()->where('device_id', $device->getKey())->first();
    }

    public function traccarIdFor(Device $device): ?int
    {
        $mapping = $this->forDevice($device);

        return $mapping?->traccar_id !== null ? (int) $mapping->traccar_id : null;
    }

    /** Look up by the Traccar uniqueId (used by the event-forward ingestion to find our device). */
    public function byUniqueId(string $uniqueId): ?TraccarDevice
    {
        return TraccarDevice::query()->where('unique_id', $uniqueId)->first();
    }

    /**
     * Register the device in Traccar (idempotent) and persist the mapping. `uniqueId` defaults to the
     * device serial; production passes the device IMEI.
     */
    public function register(Device $device, ?string $uniqueId = null): TraccarDevice
    {
        $existing = $this->forDevice($device);
        if ($existing !== null && $existing->traccar_id !== null) {
            return $existing;
        }

        $uid = $uniqueId ?? (string) $device->serial;
        $traccarId = $this->traccar->registerDevice((string) ($device->location_label ?? $device->serial), $uid);

        /** @var TraccarDevice $mapping */
        $mapping = TraccarDevice::query()->updateOrCreate(
            ['device_id' => $device->getKey()],
            ['traccar_id' => $traccarId, 'unique_id' => $uid, 'registered_at' => $this->clock->now()],
        );

        return $mapping;
    }

    /**
     * Adopt an EXISTING Traccar device into the platform: persist the mapping to the given Traccar id
     * WITHOUT registering a new device in Traccar (the caller has already confirmed it exists, via
     * findDeviceByUniqueId). Idempotent per device. Used by the reconciliation flow for devices created
     * directly in the Traccar UI. Contrast with register(), which provisions a NEW Traccar device.
     */
    public function adopt(Device $device, int $traccarId, string $uniqueId): TraccarDevice
    {
        /** @var TraccarDevice $mapping */
        $mapping = TraccarDevice::query()->updateOrCreate(
            ['device_id' => $device->getKey()],
            ['traccar_id' => $traccarId, 'unique_id' => $uniqueId, 'registered_at' => $this->clock->now()],
        );

        return $mapping;
    }
}
