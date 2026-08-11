<?php

namespace App\Console\Commands;

use App\Domain\DeviceComm\Exceptions\TraccarDeviceNotFoundException;
use App\Domain\DeviceComm\Services\TraccarReconciliationService;
use App\Domain\Devices\DTOs\DeviceRegistrationData;
use App\Domain\Devices\Enums\DriverType;
use Illuminate\Console\Command;

/**
 * Official reconciliation tool for devices that were added directly into Traccar (e.g. via the Traccar
 * web UI) and have no Laravel record. Adopts the existing Traccar device by uniqueId/IMEI — it never
 * creates a second Traccar device. Idempotent: re-running for an already-mapped uniqueId is a no-op.
 *
 *   php artisan devices:reconcile-traccar 868184062169571 \
 *       --serial=22061138 --model=1 --sim-phone=+994505384489 [--firmware=2.6.6] [--label="Sinam Test"]
 */
class ReconcileTraccarDeviceCommand extends Command
{
    protected $signature = 'devices:reconcile-traccar
        {unique_id : Traccar uniqueId / device IMEI to adopt}
        {--serial= : Device serial (required)}
        {--model= : device_model_id (required)}
        {--sim-phone= : SIM phone, format +994######### (required)}
        {--driver=traccar : Driver type (traccar|sms|ble)}
        {--sim-operator= : sim_operator_id (optional)}
        {--firmware= : Firmware version (optional)}
        {--region= : region_id (optional)}
        {--label= : location_label (optional)}';

    protected $description = 'Adopt an existing Traccar device (by uniqueId/IMEI) into Laravel: create the device + traccar_devices mapping without duplicating the Traccar device.';

    public function handle(TraccarReconciliationService $service): int
    {
        $uniqueId = (string) $this->argument('unique_id');
        $serial = (string) $this->option('serial');
        $model = $this->option('model');
        $simPhone = (string) $this->option('sim-phone');

        if ($serial === '' || $model === null || $simPhone === '') {
            $this->error('Missing required options: --serial, --model, --sim-phone.');

            return self::INVALID;
        }

        $data = new DeviceRegistrationData(
            serial: $serial,
            deviceModelId: (int) $model,
            simPhone: $simPhone,
            driverType: DriverType::from((string) $this->option('driver')),
            simOperatorId: $this->option('sim-operator') !== null ? (int) $this->option('sim-operator') : null,
            firmwareVersion: $this->option('firmware') ?: null,
            regionId: $this->option('region') !== null ? (int) $this->option('region') : null,
            locationLabel: $this->option('label') ?: null,
        );

        try {
            $result = $service->reconcile($data, $uniqueId, null);
        } catch (TraccarDeviceNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s Laravel device #%d  <->  Traccar id %d  (uniqueId %s)',
            $result->created ? 'Reconciled:' : 'Already mapped:',
            $result->device->getKey(),
            (int) $result->mapping->traccar_id,
            (string) $result->mapping->unique_id,
        ));

        return self::SUCCESS;
    }
}
