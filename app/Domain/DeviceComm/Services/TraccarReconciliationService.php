<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\DeviceComm\Contracts\TraccarClient;
use App\Domain\DeviceComm\DTOs\DeviceReconciliationResult;
use App\Domain\DeviceComm\Exceptions\TraccarDeviceNotFoundException;
use App\Domain\Devices\DTOs\DeviceRegistrationData;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceProvisioningService;
use Illuminate\Support\Facades\DB;

/**
 * Reconciliation flow: adopts a device that ALREADY exists in Traccar (e.g. created directly in the
 * Traccar web UI, or pre-provisioned out of band) into the Laravel platform. It looks the existing
 * Traccar device up by its uniqueId, creates the Laravel `devices` row, and writes the `traccar_devices`
 * mapping to the EXISTING Traccar id. It never creates a second Traccar device.
 *
 * This is the inbound counterpart to TraccarDeviceMapper::register() (Laravel→Traccar, which provisions
 * a NEW Traccar device). The event-forward webhook and its security model are unchanged: it still never
 * auto-creates devices from telemetry — once this mapping exists, the webhook simply stops ignoring the
 * device because TraccarDeviceMapper::byUniqueId() now resolves it.
 *
 * Idempotent on the Traccar uniqueId: re-running for an already-mapped uniqueId returns the existing
 * device untouched (`created = false`), so it is safe to retry.
 */
final class TraccarReconciliationService
{
    public function __construct(
        private readonly DeviceProvisioningService $provisioning,
        private readonly TraccarDeviceMapper $mapper,
        private readonly TraccarClient $traccar,
    ) {}

    public function reconcile(DeviceRegistrationData $data, string $uniqueId, ?AdminUser $admin = null): DeviceReconciliationResult
    {
        // Idempotency: if this Traccar uniqueId is already mapped, return the existing device untouched.
        $existing = $this->mapper->byUniqueId($uniqueId);
        if ($existing !== null) {
            /** @var Device $device */
            $device = Device::query()->findOrFail($existing->device_id);

            return new DeviceReconciliationResult($device, $existing, created: false);
        }

        // Adopt-only: the device MUST already exist in Traccar. We never create a Traccar device here.
        $remote = $this->traccar->findDeviceByUniqueId($uniqueId);
        if ($remote === null) {
            throw new TraccarDeviceNotFoundException($uniqueId);
        }

        // Create the Laravel device and map it to the EXISTING Traccar id atomically.
        return DB::transaction(function () use ($data, $remote, $uniqueId, $admin): DeviceReconciliationResult {
            $device = $this->provisioning->register($data, $admin);
            $mapping = $this->mapper->adopt($device, $remote->id, $uniqueId);

            return new DeviceReconciliationResult($device, $mapping, created: true);
        });
    }
}
