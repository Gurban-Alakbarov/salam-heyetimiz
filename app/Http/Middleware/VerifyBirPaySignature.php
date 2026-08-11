<?php

namespace App\Http\Middleware;

use App\Domain\Payments\Exceptions\SignatureInvalidException;
use App\Domain\Payments\Services\PaymentLogger;
use App\Domain\Payments\Support\BirPaySignature;
use App\Domain\Payments\Support\PaymentSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the BirPay webhook X-Signature (Base64 HMAC-SHA256) over the RAW bytes captured by CaptureRawBody.
 * The shared secret + IP allowlist come ONLY from Settings (no config/.env). On failure: records the attempt
 * in payment_logs (signature_valid=0), emits a metric partitioned by ip-in-allowlist, then 401s.
 */
class VerifyBirPaySignature
{
    public function __construct(
        private readonly PaymentLogger $logger,
        private readonly PaymentSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $raw = (string) $request->attributes->get('raw_body', $request->getContent());
        $provided = $request->header('X-Signature');
        $signature = new BirPaySignature($this->settings->webhookSecret());

        if (! $signature->verify($raw, $provided)) {
            $ip = $request->ip();

            $this->logger->logInbound(
                orderId: null,
                endpoint: '/v1/payments/webhook',
                payload: $this->safePayload($raw),
                signatureProvided: $provided !== null && $provided !== '',
                signatureValid: false,
                ip: $ip,
                httpStatus: 401,
            );

            Log::warning('birpay.webhook.signature_invalid', [
                'ip' => $ip,
                'ip_in_allowlist' => $this->ipInAllowlist($ip),
            ]);

            throw new SignatureInvalidException('Invalid or missing BirPay webhook signature.');
        }

        return $next($request);
    }

    private function ipInAllowlist(?string $ip): bool
    {
        $allowlist = $this->settings->ipAllowlist();

        return $allowlist === [] || ($ip !== null && in_array($ip, $allowlist, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function safePayload(string $raw): array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
