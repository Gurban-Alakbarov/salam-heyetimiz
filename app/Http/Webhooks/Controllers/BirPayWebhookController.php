<?php

namespace App\Http\Webhooks\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payments\DTOs\KapitalCallbackData;
use App\Domain\Payments\Services\PaymentCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

/**
 * POST /v1/payments/webhook — birpayWebhook. The X-Signature is already verified by VerifyBirPaySignature
 * over the raw bytes captured by CaptureRawBody. BirPay payload: {event, payload:{id, status, type,
 * paymentMethod}}. We de-duplicate + persist + audit, then hand off to the verify-via-getOrderStatus pipeline
 * — never trusting the body for state (R-PAY-04). Always 200 so BirPay does not pointlessly retry.
 */
class BirPayWebhookController
{
    public function __invoke(Request $request, PaymentCallbackService $callbacks, AuditLogger $audit): JsonResponse
    {
        $raw = (string) $request->attributes->get('raw_body', $request->getContent());
        $decoded = json_decode($raw, true);

        $event = is_array($decoded) ? (string) ($decoded['event'] ?? '') : '';
        $payload = is_array($decoded) && is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        $paymentId = (string) ($payload['id'] ?? '');

        if ($event === '' || $paymentId === '') {
            return $this->badRequest();
        }

        // Map the BirPay payload onto the callback model (orderId = BirPay payment id).
        $data = KapitalCallbackData::fromArray(['orderId' => $paymentId, 'status' => (string) ($payload['status'] ?? '')] + $payload);
        $callback = $callbacks->receive($data, $raw, $request->ip());

        $audit->record('payment.webhook_received', [
            'event' => $event,
            'payment_id' => $paymentId,
            'callback_id' => (int) $callback->id,
            'outcome' => $callback->outcome->value,
        ]);

        return response()->json(['status' => 'accepted'], 200);
    }

    private function badRequest(): JsonResponse
    {
        $requestId = Context::get('request_id');

        return response()->json([
            'error' => [
                'code' => 'bad_request',
                'message_key' => 'errors.bad_request',
                'message' => __('errors.bad_request'),
                'details' => null,
                'request_id' => is_string($requestId) ? $requestId : null,
            ],
        ], 400);
    }
}
