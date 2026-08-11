<?php

namespace App\Exceptions\Renderers;

use App\Exceptions\Contracts\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

/**
 * Renders exceptions into the binding error envelope (R-API-05, OpenAPI v1.1.0):
 *   { error: { code, message_key, message, details, request_id } }
 *
 * `message` is the server-side translation of `message_key` (v1.1). The contract
 * clients rely on is `code` + `message_key`; `message` is convenience and is
 * scheduled for removal in OpenAPI v1.2 (R-API-09).
 */
class ApiExceptionRenderer
{
    public function renderDomain(DomainException $e, Request $request): JsonResponse
    {
        $details = $e->details();

        return response()->json([
            'error' => [
                'code' => $e->errorCode(),
                'message_key' => $e->messageKey(),
                'message' => __($e->messageKey(), $details ?? []),
                'details' => $details,
                'request_id' => $this->requestId(),
            ],
        ], $e->httpStatus(), $e->headers());
    }

    public function renderUnauthenticated(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'unauthenticated',
                'message_key' => 'errors.unauthenticated',
                'message' => __('errors.unauthenticated'),
                'request_id' => $this->requestId(),
            ],
        ], 401);
    }

    public function renderValidation(ValidationException $e, Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'validation_failed',
                'message_key' => 'errors.validation_failed',
                'message' => __('errors.validation_failed'),
                'fields' => $e->errors(),
                'request_id' => $this->requestId(),
            ],
        ], 422);
    }

    private function requestId(): ?string
    {
        $id = Context::get('request_id');

        return is_string($id) ? $id : null;
    }
}
