<?php

namespace App\Domain\Auth\Adapters;

use App\Domain\Auth\Contracts\OtpTransport;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Production OTP transport: posts the rendered OTP message to the configured AZ SMS
 * provider (config/integrations/sms.php). Transport, auth and error handling are real;
 * the exact provider field mapping is the Phase-0 sandbox-confirmable surface — not a
 * mock (the test double is FakeOtpTransport, bound only in testing/fake mode).
 */
final class SmsOtpTransport implements OtpTransport
{
    public function send(string $phone, string $code, string $locale): void
    {
        $baseUrl = (string) config('integrations.sms.base_url');
        if ($baseUrl === '') {
            throw new RuntimeException('SMS provider base_url is not configured.');
        }

        $response = Http::asJson()
            ->withToken((string) config('integrations.sms.api_key'))
            ->timeout((int) config('integrations.sms.timeout_seconds', 10))
            ->post($baseUrl, [
                'sender' => config('integrations.sms.sender_id'),
                'to' => $phone,
                'message' => $this->renderMessage($code, $locale),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS provider rejected the OTP dispatch: HTTP '.$response->status());
        }
    }

    private function renderMessage(string $code, string $locale): string
    {
        return trans('auth.otp_sms', ['code' => $code], $locale);
    }
}
