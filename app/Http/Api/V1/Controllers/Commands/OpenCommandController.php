<?php

namespace App\Http\Api\V1\Controllers\Commands;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Actions\OpenDevice;
use App\Domain\DeviceComm\Actions\SubmitOpenFeedback;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Queries\OpenCommandQuery;
use App\Http\Api\V1\Requests\Commands\OpenDeviceRequest;
use App\Http\Api\V1\Requests\Commands\SubmitFeedbackRequest;
use App\Http\Resources\OpenCommandResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OpenCommandController
{
    /** POST /v1/devices/{deviceId}/open — openDevice (202; idempotent; cooldown; R-DOM-05). */
    public function open(OpenDeviceRequest $request, int $deviceId, OpenDevice $action): JsonResponse
    {
        $device = Device::query()->findOrFail($deviceId);
        abort_unless($request->user()?->can('view', $device) ?? false, 404);

        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            throw ValidationException::withMessages(['Idempotency-Key' => __('errors.idempotency_key_required')]);
        }

        $accepted = $action->handle(
            device: $device,
            user: $request->user(),
            idempotencyKey: $key,
            clientAppVersion: $request->input('client_app_version'),
            clientIp: $request->ip(),
            direction: CommandDirection::tryFrom((string) $request->input('direction', 'open')) ?? CommandDirection::Open,
        );

        return response()->json([
            'command_id' => (int) $accepted->command->getKey(),
            'state' => $accepted->command->state->value,
            'expected_completion_ms' => $accepted->expectedCompletionMs,
            'driver_confirms_actuation' => $accepted->driverConfirmsActuation,
            'websocket_channel' => $accepted->websocketChannel,
        ], 202);
    }

    /** GET /v1/devices/{deviceId}/commands — listDeviceCommands (caller's history). */
    public function index(Request $request, int $deviceId, OpenCommandQuery $query): JsonResponse
    {
        $device = Device::query()->findOrFail($deviceId);
        abort_unless($request->user()?->can('view', $device) ?? false, 404);

        $result = $query->forUserDevice(
            userId: (int) $request->user()->getKey(),
            deviceId: $deviceId,
            state: $request->query('state'),
            since: $request->query('since'),
            until: $request->query('until'),
            limit: $this->limit($request),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => OpenCommandResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** GET /v1/commands/{commandId} — getCommand (terminal-state polling). */
    public function show(Request $request, int $commandId): OpenCommandResource
    {
        $command = OpenCommand::query()->findOrFail($commandId);
        abort_unless($request->user()?->can('view', $command) ?? false, 404);

        return new OpenCommandResource($command);
    }

    /** POST /v1/commands/{commandId}/feedback — submitOpenFeedback (201 new / 200 replay). */
    public function feedback(SubmitFeedbackRequest $request, int $commandId, SubmitOpenFeedback $action): JsonResponse
    {
        $command = OpenCommand::query()->findOrFail($commandId);
        abort_unless($request->user()?->can('submitFeedback', $command) ?? false, 404);

        $result = $action->handle(
            $command,
            $request->user(),
            (bool) $request->boolean('gate_moved'),
            $request->input('comment'),
        );

        return response()->json(null, $result['replayed'] ? 200 : 201);
    }

    private function limit(Request $request): int
    {
        return max(1, min(100, (int) $request->query('limit', 25)));
    }
}
