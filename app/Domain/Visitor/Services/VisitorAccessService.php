<?php

namespace App\Domain\Visitor\Services;

use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Events\OpenCommandIssued;
use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Services\DriverResolver;
use App\Domain\DeviceComm\Services\ExpectedCompletionEstimator;
use App\Domain\DeviceComm\Services\OpenCommandService;
use App\Domain\DeviceComm\Support\AcceptedOpenCommand;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Visitor\Enums\VisitorUsageResult;
use App\Domain\Visitor\Exceptions\VisitorLinkUnavailableException;
use App\Domain\Visitor\Models\VisitorLink;
use App\Domain\Visitor\Models\VisitorLinkUsage;
use App\Domain\Visitor\Support\VisitorToken;
use App\Support\Time\Clock;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The public (no-auth) side of a visitor link: resolve a token, report its status, and open the barrier.
 *
 * Opening reuses the EXACT mobile/admin relay pipeline (OpenCommandService::issueVisitor →
 * DispatchOpenCommandJob → CommandDispatcher → TraccarDriver) — no special or weaker path. The link IS
 * the authorisation, so there is no roster/subscription gate; instead every attempt is validated
 * (revoked / expired / usage / device-active) under a row lock and written to visitor_link_usages.
 * usage_count is NOT incremented here — it advances only when the open is CONFIRMED successful
 * (IncrementVisitorUsageOnOpenCompleted), so a courier's one-time link is never burned by an offline unit.
 */
final class VisitorAccessService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly OpenCommandService $commands,
        private readonly DriverResolver $drivers,
        private readonly ExpectedCompletionEstimator $estimator,
    ) {}

    /** Resolve a plaintext token to its link, or 404. Read-only (no usage recorded). */
    public function resolve(string $token): VisitorLink
    {
        $link = VisitorLink::query()
            ->with(['device.owner', 'createdByUser'])
            ->where('token_hash', VisitorToken::hash($token))
            ->first();

        if ($link === null) {
            throw new VisitorLinkUnavailableException(VisitorUsageResult::NotFound);
        }

        return $link;
    }

    /** Why the link cannot be used now, or null if it is live and the device is openable. Read-only. */
    public function unavailableReason(VisitorLink $link, ?CarbonInterface $now = null): ?VisitorUsageResult
    {
        $now ??= $this->clock->now();

        return match (true) {
            $link->isRevoked() => VisitorUsageResult::Revoked,
            $link->isExpired($now) => VisitorUsageResult::Expired,
            $link->isUsageExhausted() => VisitorUsageResult::UsageExceeded,
            ! $this->deviceOpenable($link->device) => VisitorUsageResult::DeviceInactive,
            default => null,
        };
    }

    /**
     * Open the barrier for a valid link. Locks the link row, re-validates (incl. in-flight opens against
     * max_usage), issues the relay through the shared pipeline, and audits the attempt. Throws
     * VisitorLinkUnavailableException (already audited) when the link cannot be used.
     */
    public function open(string $token, ?string $ip, ?string $userAgent): AcceptedOpenCommand
    {
        try {
            return DB::transaction(function () use ($token, $ip, $userAgent): AcceptedOpenCommand {
                $link = VisitorLink::query()
                    ->with('device')
                    ->where('token_hash', VisitorToken::hash($token))
                    ->lockForUpdate()
                    ->first();

                if ($link === null) {
                    throw new VisitorLinkUnavailableException(VisitorUsageResult::NotFound);
                }

                $reason = $this->openBlockedReason($link);
                if ($reason !== null) {
                    // Audited outside the transaction (below) so the record survives the rollback.
                    throw new VisitorLinkUnavailableException($reason);
                }

                /** @var Device $device */
                $device = $link->device;
                $driver = $this->drivers->driverTypeFor($device);

                $command = $this->commands->issueVisitor(
                    device: $device,
                    driver: $driver,
                    direction: CommandDirection::Open,
                    clientIp: $ip,
                    metadata: ['visitor_link_id' => (int) $link->getKey()],
                );

                DispatchOpenCommandJob::dispatch((int) $command->getKey());
                OpenCommandIssued::dispatch($command);

                // The accepted attempt is audited inside the transaction — it commits atomically with the issue.
                $this->recordUsage($link, (int) $command->getKey(), $ip, $userAgent, VisitorUsageResult::Accepted);

                return new AcceptedOpenCommand(
                    command: $command,
                    expectedCompletionMs: $this->estimator->estimate($device, $driver),
                    driverConfirmsActuation: $driver->confirmsActuation(),
                    websocketChannel: 'visitor.'.$link->getKey(),
                    replayed: false,
                );
            });
        } catch (VisitorLinkUnavailableException $e) {
            // A blocked attempt rolled the transaction back; record the audit row on its own so we still
            // capture who tried to use a revoked/expired/used-up link (unknown tokens have nothing to attribute).
            $this->auditBlockedAttempt($token, $ip, $userAgent, $e->reason);
            throw $e;
        }
    }

    private function auditBlockedAttempt(string $token, ?string $ip, ?string $userAgent, VisitorUsageResult $reason): void
    {
        if ($reason === VisitorUsageResult::NotFound) {
            return;
        }

        $link = VisitorLink::query()->where('token_hash', VisitorToken::hash($token))->first();
        if ($link !== null) {
            $this->recordUsage($link, null, $ip, $userAgent, $reason);
        }
    }

    /** Fetch a command's state, scoped to THIS link — a visitor can only poll opens issued by their link. */
    public function commandState(VisitorLink $link, int $commandId): OpenCommand
    {
        return OpenCommand::query()
            ->where('id', $commandId)
            ->where('source', OpenCommandSource::Visitor->value)
            ->where('metadata->visitor_link_id', (int) $link->getKey())
            ->firstOrFail();
    }

    /** Validity check under the write lock, including the max_usage reservation against in-flight opens. */
    private function openBlockedReason(VisitorLink $link): ?VisitorUsageResult
    {
        $now = $this->clock->now();

        if ($link->isRevoked()) {
            return VisitorUsageResult::Revoked;
        }
        if ($link->isExpired($now)) {
            return VisitorUsageResult::Expired;
        }
        if (! $this->deviceOpenable($link->device)) {
            return VisitorUsageResult::DeviceInactive;
        }
        if ($link->max_usage !== null && ($link->usage_count + $this->inFlightOpens($link)) >= $link->max_usage) {
            // Settled successes PLUS opens still running for this link — closes the double-click race so a
            // one-time link can never fire the relay twice.
            return VisitorUsageResult::UsageExceeded;
        }

        return null;
    }

    private function inFlightOpens(VisitorLink $link): int
    {
        return OpenCommand::query()
            ->where('device_id', $link->device_id)
            ->where('source', OpenCommandSource::Visitor->value)
            ->whereIn('state', [OpenCommandState::Queued->value, OpenCommandState::Dispatching->value])
            ->where('metadata->visitor_link_id', (int) $link->getKey())
            ->count();
    }

    private function deviceOpenable(?Device $device): bool
    {
        return $device !== null && $device->deleted_at === null && $device->status === DeviceStatus::Active;
    }

    private function recordUsage(VisitorLink $link, ?int $commandId, ?string $ip, ?string $ua, VisitorUsageResult $result): void
    {
        VisitorLinkUsage::query()->create([
            'visitor_link_id' => $link->getKey(),
            'open_command_id' => $commandId,
            'used_at' => $this->clock->now(),
            'ip' => $ip,
            'user_agent' => $ua !== null ? mb_substr($ua, 0, 512) : null,
            'result' => $result->value,
        ]);
    }
}
