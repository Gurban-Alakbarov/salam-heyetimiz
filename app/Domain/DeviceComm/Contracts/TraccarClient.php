<?php

namespace App\Domain\DeviceComm\Contracts;

use App\Domain\DeviceComm\DTOs\TraccarCommandResult;
use App\Domain\DeviceComm\DTOs\TraccarRemoteDevice;

/**
 * Vendor-replaceable Traccar REST boundary (R-ARCH-08). The concrete HTTP client talks to a self-hosted
 * Traccar; the fake is the test/dev binding. Telemetry flows the other way via the event-forward webhook
 * (not part of this contract).
 */
interface TraccarClient
{
    /** Register a device in Traccar; returns the Traccar numeric device id. */
    public function registerDevice(string $name, string $uniqueId): int;

    /** Send a text `custom` command to a Traccar device (by its Traccar id). */
    public function sendCommand(int $traccarId, string $commandText): TraccarCommandResult;

    /**
     * Fetch the device's `commandResult` events (the terminal's response text to an online command —
     * GT06 0x21). Used to confirm actuation for models Traccar does not forward result events for.
     * Newest first (highest Traccar event id first). Empty on any error.
     *
     * @return list<array{id:int, result:?string, event_time:?string}>
     */
    public function commandResults(int $traccarId, \DateTimeInterface $from, \DateTimeInterface $to): array;

    /** Look up an EXISTING Traccar device by its uniqueId (for reconciliation); null if Traccar has none. */
    public function findDeviceByUniqueId(string $uniqueId): ?TraccarRemoteDevice;
}
