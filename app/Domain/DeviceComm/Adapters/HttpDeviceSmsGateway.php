<?php

namespace App\Domain\DeviceComm\Adapters;

use App\Domain\DeviceComm\Contracts\DeviceSmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Production device-SMS gateway: posts the command to the configured AZ SMS provider
 * (config/integrations/sms.php). Real HTTP; provider field mapping is Phase-0 confirmable. Not a mock —
 * the test double is FakeDeviceSmsGateway.
 */
final class HttpDeviceSmsGateway implements DeviceSmsGateway
{
    public function sendCommand(string $phone, string $command): bool
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
                'message' => $command,
            ]);

        return $response->successful();
    }
}
