<?php

namespace App\Http\Api\V1\Controllers\Visitor;

use App\Domain\Visitor\Services\VisitorAccessService;
use App\Domain\Visitor\Support\VisitorPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no-auth) visitor endpoints, authenticated by the token in the path. Opening reuses the shared
 * relay pipeline (VisitorAccessService). Responses expose NO internal ids beyond the token-scoped
 * command id needed to poll — every payload is safe to hand to an anonymous browser.
 */
class VisitorAccessController
{
    public function __construct(private readonly VisitorAccessService $access) {}

    /** GET /v1/visit/{token} — link status for the visitor page (barrier + resident + validity). */
    public function show(string $token): JsonResponse
    {
        $link = $this->access->resolve($token); // 404 (as VisitorLinkUnavailableException) if unknown

        return response()->json(VisitorPresenter::status($link, $this->access->unavailableReason($link)));
    }

    /** POST /v1/visit/{token}/open — open the barrier through the same relay flow as the mobile app. */
    public function open(Request $request, string $token): JsonResponse
    {
        $accepted = $this->access->open($token, $request->ip(), $request->userAgent());

        return response()->json([
            'command_id' => (int) $accepted->command->getKey(),
            'state' => $accepted->command->state->value,
            'expected_completion_ms' => $accepted->expectedCompletionMs,
            'driver_confirms_actuation' => $accepted->driverConfirmsActuation,
        ], 202);
    }

    /** GET /v1/visit/{token}/command/{commandId} — poll the open's state (scoped to this link). */
    public function command(string $token, int $commandId): JsonResponse
    {
        $link = $this->access->resolve($token);
        $command = $this->access->commandState($link, $commandId);

        return response()->json([
            'command_id' => (int) $command->getKey(),
            'state' => $command->state->value,
        ]);
    }
}
