<?php

namespace App\Http\Webhooks\Controllers;

use App\Domain\DeviceComm\Services\TraccarIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Traccar event-forward webhook (v1.2): ingests positions/events to update device online status,
 * device_diagnostics, and open actuation confirmation. Authenticated by a shared secret token
 * (query `token` or header `X-Traccar-Token`) configured on Traccar's forward URL — Traccar does not
 * sign its forwards, so a constant-time token compare is the gate.
 */
class TraccarForwardController
{
    public function __invoke(Request $request, TraccarIngestionService $ingestion): JsonResponse
    {
        $expected = (string) config('integrations.traccar.forward_token');
        $provided = (string) ($request->header('X-Traccar-Token') ?? $request->query('token', ''));

        abort_unless($expected !== '' && hash_equals($expected, $provided), 401);

        $ingestion->ingest($request->all());

        return response()->json(['status' => 'ok']); // always 200 to avoid Traccar retry storms on known input
    }
}
