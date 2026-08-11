<?php

namespace App\Domain\DeviceComm\Adapters;

use App\Domain\DeviceComm\Contracts\TraccarClient;
use App\Domain\DeviceComm\DTOs\TraccarCommandResult;
use App\Domain\DeviceComm\DTOs\TraccarRemoteDevice;

/**
 * Test/dev Traccar client (R-ARCH-08) — records registrations + sent commands in memory and returns a
 * configurable result, so DeviceComm tests exercise the TraccarDriver + ingestion without a live Traccar.
 * Registered as a singleton (like FakeKapitalGateway / FakeOtpTransport).
 */
final class FakeTraccarClient implements TraccarClient
{
    private int $nextId = 1000;

    /** @var array<int, array{traccarId:int, command:string}> */
    public array $sentCommands = [];

    /** @var array<int, array{name:string, uniqueId:string, id:int}> */
    public array $registered = [];

    /** @var array<int, array{id:int, uniqueId:string, name:string, status:string}> */
    public array $remoteDevices = [];

    /** @var array<int, array{id:int, result:?string, event_time:?string}> */
    public array $commandResults = [];

    private int $nextEventId = 1;

    private TraccarCommandResult $nextResult;

    private ?string $autoResponse = null;

    public function __construct()
    {
        $this->nextResult = TraccarCommandResult::sent();
    }

    /** Configure the result the next sendCommand() calls will return. */
    public function willReturn(TraccarCommandResult $result): void
    {
        $this->nextResult = $result;
    }

    /**
     * Model the device replying to the command: each subsequent sendCommand() auto-seeds a
     * commandResult event with this text (the 0x21 response), so the polling driver can confirm.
     * Pass null to disable.
     */
    public function willRespondWith(?string $result): void
    {
        $this->autoResponse = $result;
    }

    /** Seed a device commandResult event (0x21 response text) for the polling driver to pick up. */
    public function seedCommandResult(string $result): int
    {
        $id = $this->nextEventId++;
        $this->commandResults[] = ['id' => $id, 'result' => $result, 'event_time' => null];

        return $id;
    }

    public function registerDevice(string $name, string $uniqueId): int
    {
        $id = $this->nextId++;
        $this->registered[] = ['name' => $name, 'uniqueId' => $uniqueId, 'id' => $id];

        return $id;
    }

    public function sendCommand(int $traccarId, string $commandText): TraccarCommandResult
    {
        $this->sentCommands[] = ['traccarId' => $traccarId, 'command' => $commandText];

        // Only a delivered command elicits a device response.
        if ($this->autoResponse !== null && $this->nextResult->sent) {
            $this->seedCommandResult($this->autoResponse);
        }

        return $this->nextResult;
    }

    public function commandResults(int $traccarId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $events = $this->commandResults;
        usort($events, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);

        return $events;
    }

    /** Seed a device that "already exists" in Traccar, so reconciliation tests can adopt it. */
    public function seedRemoteDevice(int $id, string $uniqueId, string $name = 'Seeded', string $status = 'online'): void
    {
        $this->remoteDevices[] = compact('id', 'uniqueId', 'name', 'status');
    }

    public function findDeviceByUniqueId(string $uniqueId): ?TraccarRemoteDevice
    {
        foreach ($this->remoteDevices as $d) {
            if ($d['uniqueId'] === $uniqueId) {
                return new TraccarRemoteDevice($d['id'], $d['uniqueId'], $d['name'], $d['status']);
            }
        }

        // Devices created via registerDevice() are also "present" in the fake Traccar.
        foreach ($this->registered as $r) {
            if ($r['uniqueId'] === $uniqueId) {
                return new TraccarRemoteDevice($r['id'], $r['uniqueId'], $r['name'], 'online');
            }
        }

        return null;
    }

    public function lastCommand(): ?string
    {
        $last = end($this->sentCommands);

        return $last === false ? null : $last['command'];
    }
}
