<?php

namespace App\Http\Api\V1\Support;

use Illuminate\Http\JsonResponse;

/**
 * The unified response envelope used by every NEW registration/auth endpoint (register, verify-email,
 * resend-otp, login). Shape: `{ success, message, data, meta, errors }`. This is the final contract the
 * Flutter client codes against — it does not change once Flutter starts.
 *
 * The legacy phone-OTP endpoints (otp/request, otp/verify, refresh, logout) keep their existing shape and
 * are NOT touched (additive coexistence); unifying them would be a breaking change for a future API
 * version, not this module.
 */
trait RespondsWithEnvelope
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function success(mixed $data = null, ?string $message = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        return $this->envelope(true, $message, $data, $meta, null, $status);
    }

    protected function failure(string $message, mixed $errors = null, int $status = 400): JsonResponse
    {
        return $this->envelope(false, $message, null, null, $errors, $status);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function envelope(bool $success, ?string $message, mixed $data, ?array $meta, mixed $errors, int $status): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }
}
