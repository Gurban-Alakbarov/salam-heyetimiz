<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\DiagnosticSource;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Models\DeviceDiagnostic;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Support\Time\Clock;
use Illuminate\Support\Carbon;

/**
 * Ingests a Traccar event-forward payload (v1.2): records a device_diagnostics row, updates the device's
 * online status, and — when the telemetry shows the output fired — confirms a recent `dispatched` open as
 * `opened` (actuation read-back, R-GSM-03). Unknown devices are ignored.
 */
final class TraccarIngestionService
{
    public function __construct(
        private readonly TraccarDeviceMapper $mapper,
        private readonly OpenCommandService $commands,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  a Traccar forward body (position + device)
     * @return bool whether a known device was matched
     */
    public function ingest(array $payload): bool
    {
        $uniqueId = data_get($payload, 'device.uniqueId');
        $mapping = is_string($uniqueId) ? $this->mapper->byUniqueId($uniqueId) : null;

        if ($mapping === null) {
            return false; // unknown device — ignore
        }

        /** @var Device|null $device */
        $device = Device::query()->find($mapping->device_id);
        if ($device === null) {
            return false;
        }

        $attributes = array_merge(
            (array) data_get($payload, 'attributes', []),
            (array) data_get($payload, 'position.attributes', []),
        );
        $online = data_get($payload, 'device.status') !== 'offline';
        $reportedAt = $this->parseTime(data_get($payload, 'position.serverTime') ?? data_get($payload, 'position.deviceTime') ?? data_get($payload, 'position.fixTime'));

        $this->recordDiagnostic($device, $attributes, $online, $reportedAt);
        $this->updateDeviceStatus($device, $online, $attributes, $reportedAt);

        if ($online && $this->outputIsOn($attributes)) {
            $this->confirmRecentOpen($device, $reportedAt);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordDiagnostic(Device $device, array $attributes, bool $online, Carbon $reportedAt): void
    {
        DeviceDiagnostic::query()->create([
            'partition_key' => (int) $reportedAt->format('Ym'),
            'device_id' => $device->getKey(),
            'source' => DiagnosticSource::DeviceInitiated->value,
            'online' => $online,
            'signal_strength' => $this->intAttr($attributes, ['sat', 'rssi', 'signal']),
            'battery_level' => $this->intAttr($attributes, ['battery', 'batteryLevel']),
            'firmware_version' => is_string($attributes['version'] ?? null) ? $attributes['version'] : null,
            'raw' => $attributes === [] ? null : $attributes,
            'reported_at' => $reportedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateDeviceStatus(Device $device, bool $online, array $attributes, Carbon $reportedAt): void
    {
        if (! $online) {
            return;
        }

        $device->forceFill([
            'last_online_at' => $reportedAt,
            'last_signal_strength' => $this->intAttr($attributes, ['sat', 'rssi', 'signal']) ?? $device->last_signal_strength,
        ])->save();
    }

    private function confirmRecentOpen(Device $device, Carbon $reportedAt): void
    {
        $window = (int) config('integrations.traccar.actuation_confirm_window_seconds', 30);

        $command = OpenCommand::query()
            ->where('device_id', $device->getKey())
            ->where('state', OpenCommandState::Dispatched->value)
            ->where('requested_at', '>=', $reportedAt->copy()->subSeconds($window))
            ->orderByDesc('id')
            ->first();

        if ($command !== null) {
            $this->commands->confirmActuation($command);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function outputIsOn(array $attributes): bool
    {
        $key = (string) config('integrations.traccar.output_attribute', 'output');
        $value = $attributes[$key] ?? null;

        return $value === true || $value === 1 || $value === '1' || (is_int($value) && $value > 0);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $keys
     */
    private function intAttr(array $attributes, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($attributes[$key]) && is_numeric($attributes[$key])) {
                return (int) $attributes[$key];
            }
        }

        return null;
    }

    private function parseTime(mixed $value): Carbon
    {
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // fall through to now
            }
        }

        return Carbon::instance($this->clock->now()->toDateTime());
    }
}
