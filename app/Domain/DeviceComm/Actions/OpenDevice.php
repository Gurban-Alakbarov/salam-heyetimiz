<?php

namespace App\Domain\DeviceComm\Actions;

use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Events\OpenCommandIssued;
use App\Domain\DeviceComm\Exceptions\OpenNotPermittedException;
use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Services\CooldownGuard;
use App\Domain\DeviceComm\Services\DriverResolver;
use App\Domain\DeviceComm\Services\ExpectedCompletionEstimator;
use App\Domain\DeviceComm\Services\OpenCommandService;
use App\Domain\DeviceComm\Support\AcceptedOpenCommand;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SuspensionReason;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Domain\Users\Models\User;
use App\Support\Geo\GeoDistance;

/**
 * Issue an open command (openDevice). Enforces the open-permission rule (R-DOM-05) via the §13.9
 * read, the per-(user, device)/per-device cooldowns (R-DOM-09), and idempotency (uq on
 * user_id + idempotency_key). Creates the queued command and enqueues dispatch onto the `open` queue;
 * the driver actually fires in the GSM batch.
 */
final class OpenDevice
{
    public function __construct(
        private readonly SubscriptionStatusQuery $access,
        private readonly SubscriptionQuery $subscriptions,
        private readonly CooldownGuard $cooldown,
        private readonly DriverResolver $drivers,
        private readonly OpenCommandService $commands,
        private readonly ExpectedCompletionEstimator $estimator,
    ) {}

    public function handle(Device $device, User $user, string $idempotencyKey, ?string $clientAppVersion, ?string $clientIp, CommandDirection $direction = CommandDirection::Open, ?float $latitude = null, ?float $longitude = null, ?float $accuracy = null): AcceptedOpenCommand
    {
        $deviceId = (int) $device->getKey();
        $userId = (int) $user->getKey();

        // Idempotent replay → return the existing command without re-cooling or re-dispatching.
        $existing = $this->commands->findByIdempotencyKey($userId, $idempotencyKey);
        if ($existing !== null) {
            return $this->accept($device, $existing, $userId, replayed: true);
        }

        $deviceUser = DeviceUser::query()
            ->where('device_id', $deviceId)
            ->where('user_id', $userId)
            ->where('status', DeviceUserStatus::Active->value)
            ->first();

        if ($deviceUser === null) {
            throw OpenNotPermittedException::forbidden();
        }

        $this->assertCanOpen($deviceId, $userId);

        // GEOFENCE-1 — distance gate. Runs AFTER core authorization (roster + R-DOM-05) and BEFORE
        // the cooldown/issue, so an out-of-zone or missing-location caller never reserves a cooldown
        // or a command. The server holds the radius + device coords; the client is never trusted.
        $this->assertWithinGeofence($device, $latitude, $longitude, $accuracy);

        // Cooldown is enforced (and reserved) only once we know the open is permitted.
        $this->cooldown->enforce($userId, $deviceId);

        $driverType = $this->drivers->driverTypeFor($device);
        $subscription = $this->subscriptions->forUserDevice($userId, $deviceId);

        $command = $this->commands->issue(
            device: $device,
            user: $user,
            deviceUser: $deviceUser,
            subscription: $subscription,
            driver: $driverType,
            idempotencyKey: $idempotencyKey,
            source: OpenCommandSource::Mobile,
            clientAppVersion: $clientAppVersion,
            clientIp: $clientIp,
            direction: $direction,
        );

        DispatchOpenCommandJob::dispatch((int) $command->getKey());
        OpenCommandIssued::dispatch($command);

        return $this->accept($device, $command, $userId, replayed: false);
    }

    private function assertCanOpen(int $deviceId, int $userId): void
    {
        $result = $this->access->for($userId, $deviceId);
        if ($result->canOpen) {
            return;
        }

        throw match ($result->suspensionReason) {
            SuspensionReason::DeviceDisabled => OpenNotPermittedException::deviceDisabled($deviceId),
            SuspensionReason::SubscriptionExpired,
            SuspensionReason::OwnerSubExpiredOthersActive => OpenNotPermittedException::subscriptionRequired($deviceId),
            default => OpenNotPermittedException::forbidden(),
        };
    }

    /**
     * GEOFENCE-1 (D2/D4) — server-side distance gate. No-op when the device has geofencing off. When
     * on: the caller MUST supply a location; the fix is accepted only when the nearest edge of its
     * accuracy circle is within the device radius (distance − accuracy ≤ radius). A fix whose accuracy
     * is larger than the whole radius is rejected as too imprecise to trust. The device coords + radius
     * are read from the server; the client's numbers never widen the allowed zone beyond its accuracy.
     */
    private function assertWithinGeofence(Device $device, ?float $latitude, ?float $longitude, ?float $accuracy): void
    {
        if (! $device->geofence_enabled) {
            return;
        }

        $deviceId = (int) $device->getKey();

        // Enabled without a reference point/radius = misconfiguration (config validation prevents it).
        // Fail closed rather than open.
        if ($device->latitude === null || $device->longitude === null || $device->geofence_radius_m === null) {
            throw OpenNotPermittedException::forbidden();
        }

        if ($latitude === null || $longitude === null) {
            throw OpenNotPermittedException::locationRequired($deviceId);
        }

        $radius = (float) $device->geofence_radius_m;
        $margin = $accuracy ?? 0.0;

        if ($margin > $radius) {
            throw OpenNotPermittedException::locationImprecise($deviceId);
        }

        $distance = GeoDistance::metersBetween(
            $latitude,
            $longitude,
            (float) $device->latitude,
            (float) $device->longitude,
        );

        if (($distance - $margin) > $radius) {
            throw OpenNotPermittedException::outsideGeofence($deviceId);
        }
    }

    private function accept(Device $device, OpenCommand $command, int $userId, bool $replayed): AcceptedOpenCommand
    {
        $driverType = $command->driver;

        return new AcceptedOpenCommand(
            command: $command,
            expectedCompletionMs: $this->estimator->estimate($device, $driverType),
            driverConfirmsActuation: $driverType->confirmsActuation(),
            websocketChannel: "private-user.{$userId}",
            replayed: $replayed,
        );
    }
}
