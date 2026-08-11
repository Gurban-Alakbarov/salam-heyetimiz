<?php

namespace App\Domain\DeviceComm\Actions;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Events\OpenCommandIssued;
use App\Domain\DeviceComm\Exceptions\OpenNotPermittedException;
use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Services\CooldownGuard;
use App\Domain\DeviceComm\Services\DriverResolver;
use App\Domain\DeviceComm\Services\ExpectedCompletionEstimator;
use App\Domain\DeviceComm\Services\OpenCommandService;
use App\Domain\DeviceComm\Support\AcceptedOpenCommand;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SuspensionReason;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Domain\Users\Models\User;

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

    public function handle(Device $device, User $user, string $idempotencyKey, ?string $clientAppVersion, ?string $clientIp, CommandDirection $direction = CommandDirection::Open): AcceptedOpenCommand
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

    private function accept(Device $device, \App\Domain\DeviceComm\Models\OpenCommand $command, int $userId, bool $replayed): AcceptedOpenCommand
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
