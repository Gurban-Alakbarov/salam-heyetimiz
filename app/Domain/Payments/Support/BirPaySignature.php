<?php

namespace App\Domain\Payments\Support;

/**
 * BirPay webhook signature (X-Signature) — Base64(HMAC-SHA256(rawBody, sharedSecret)), per the official Java
 * sample. Verify-only over the raw bytes (constant-time). The secret comes from Settings, never config/.env.
 */
final class BirPaySignature
{
    public function __construct(private readonly string $secret) {}

    public function sign(string $rawBody): string
    {
        return base64_encode(hash_hmac('sha256', $rawBody, $this->secret, true));
    }

    public function verify(string $rawBody, ?string $providedSignature): bool
    {
        if ($this->secret === '' || $providedSignature === null || $providedSignature === '') {
            return false;
        }

        return hash_equals($this->sign($rawBody), trim($providedSignature));
    }
}
