<?php

use App\Http\Webhooks\Controllers\TraccarForwardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks — prefix /v1 (group "webhook")
|--------------------------------------------------------------------------
| Inbound, signature-authenticated (no JWT). The webhook group runs CaptureRawBody first (raw bytes).
*/

// Traccar telemetry event-forward (v1.2) — shared-secret token, ingestion in the controller.
Route::post('traccar/forward', TraccarForwardController::class)->name('traccarForward');

// smsInbound (smsProviderSignature) lands with the GSM/SMS-reply increment.
