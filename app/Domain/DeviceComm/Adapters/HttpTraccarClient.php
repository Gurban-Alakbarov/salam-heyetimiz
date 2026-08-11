<?php

namespace App\Domain\DeviceComm\Adapters;

use App\Domain\DeviceComm\Contracts\TraccarClient;
use App\Domain\DeviceComm\DTOs\TraccarCommandResult;
use App\Domain\DeviceComm\DTOs\TraccarRemoteDevice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Production Traccar REST client (config/integrations/traccar.php). Real HTTP + bearer auth; the exact
 * command framing for the UMKa is the Phase-0 T1-confirmable surface — not a mock (the test double is
 * FakeTraccarClient, bound only in testing / when TRACCAR_DRIVER=fake).
 */
final class HttpTraccarClient implements TraccarClient
{
    public function registerDevice(string $name, string $uniqueId): int
    {
        $response = $this->client()->post($this->url('/api/devices'), [
            'name' => $name,
            'uniqueId' => $uniqueId,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Traccar device registration failed: HTTP '.$response->status());
        }

        return (int) $response->json('id');
    }

    public function sendCommand(int $traccarId, string $commandText): TraccarCommandResult
    {
        $response = $this->client()->post($this->url('/api/commands/send'), [
            'deviceId' => $traccarId,
            'type' => 'custom',
            'attributes' => ['data' => $commandText],
        ]);

        // Traccar: 200 = delivered to a live device; 202 = accepted/queued (device offline).
        return match (true) {
            $response->status() === 202 => TraccarCommandResult::queued(),
            $response->successful() => TraccarCommandResult::sent((string) $response->json('id')),
            default => TraccarCommandResult::failed('traccar_http_'.$response->status()),
        };
    }

    public function commandResults(int $traccarId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        // Traccar's /api/reports/* negotiate on Accept: without an explicit application/json the report
        // is returned as an XLSX spreadsheet (json() would then be null). asJson() only sets
        // Content-Type, so force the Accept header here.
        $response = $this->client()->accept('application/json')->get($this->url('/api/reports/events'), [
            'deviceId' => $traccarId,
            'type' => 'commandResult',
            'from' => $from->format('Y-m-d\TH:i:s\Z'),
            'to' => $to->format('Y-m-d\TH:i:s\Z'),
        ]);

        if ($response->failed()) {
            return [];
        }

        $rows = $response->json();
        if (! is_array($rows)) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $result = data_get($row, 'attributes.result');
            $events[] = [
                'id' => (int) ($row['id'] ?? 0),
                'result' => is_string($result) ? $result : null,
                'event_time' => isset($row['eventTime']) ? (string) $row['eventTime'] : null,
            ];
        }

        // Newest first — correlate by the monotonically increasing Traccar event id.
        usort($events, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);

        return $events;
    }

    public function findDeviceByUniqueId(string $uniqueId): ?TraccarRemoteDevice
    {
        $response = $this->client()->get($this->url('/api/devices'), ['uniqueId' => $uniqueId]);

        if ($response->failed()) {
            throw new RuntimeException('Traccar device lookup failed: HTTP '.$response->status());
        }

        $rows = $response->json();
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        /** @var array<string, mixed> $row */
        $row = $rows[0];

        return new TraccarRemoteDevice(
            id: (int) ($row['id'] ?? 0),
            uniqueId: (string) ($row['uniqueId'] ?? $uniqueId),
            name: (string) ($row['name'] ?? ''),
            status: isset($row['status']) ? (string) $row['status'] : null,
        );
    }

    private function client()
    {
        return Http::asJson()
            ->withToken((string) config('integrations.traccar.api_token'))
            ->timeout((int) config('integrations.traccar.timeout_seconds', 10));
    }

    private function url(string $path): string
    {
        $base = (string) config('integrations.traccar.base_url');
        if ($base === '') {
            throw new RuntimeException('Traccar base_url is not configured.');
        }

        return rtrim($base, '/').$path;
    }
}
