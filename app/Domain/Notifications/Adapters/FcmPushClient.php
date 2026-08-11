<?php

namespace App\Domain\Notifications\Adapters;

use App\Domain\Notifications\Contracts\PushClient;
use App\Domain\Notifications\Enums\PushDeliveryStatus;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FCM HTTP v1 push transport (R-ARCH-08; Backend Arch §14.9). Authenticates with the Firebase service
 * account via google/auth (OAuth2 access token, cached under its ~60m expiry) and sends the hybrid
 * message to one device token. Maps the FCM outcome onto [PushDeliveryStatus]: ONLY FCM's UNREGISTERED
 * (token gone) → TokenInvalid, which drives push_invalid soft-flagging (R-NOT-14); every other non-2xx
 * → Failed (transient) so a malformed-message error never mass-invalidates devices.
 *
 * The FCM `data` map is string→string, so `ids` (a map) travels JSON-encoded — the persisted payload
 * model (type / notification_id / ids) is unchanged and the client JSON-decodes ids. No JWT / auth
 * token / PII is ever added to the payload (R-NOT-17). The device token, the OAuth2 access token, and
 * the service-account credential are NEVER logged.
 */
final class FcmPushClient implements PushClient
{
    /** OAuth2 scope for FCM HTTP v1. */
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Cache key for the short-lived OAuth2 access token (never the credential itself). */
    public const TOKEN_CACHE_KEY = 'integrations:fcm:access_token';

    /** Access-token cache TTL — comfortably inside the ~3600s FCM token lifetime. */
    private const TOKEN_TTL_SECONDS = 3000;

    public function __construct(
        private readonly string $projectId,
        private readonly string $credentialsPath,
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function send(string $token, array $notification, array $data): PushDeliveryStatus
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withToken($this->accessToken())
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send",
                    ['message' => $this->message($token, $notification, $data)],
                );
        } catch (Throwable) {
            // Transport/auth-fetch error — transient; the job keeps fanning out to the other installs.
            return PushDeliveryStatus::Failed;
        }

        if ($response->successful()) {
            return PushDeliveryStatus::Delivered;
        }

        $errorCode = (string) ($response->json('error.details.0.errorCode') ?? $response->json('error.status') ?? '');

        // Only an explicit "this registration token is gone" invalidates it (R-NOT-14) — never a
        // 400 message error, which would otherwise flag every device on a single bug.
        if ($response->status() === 404 || $errorCode === 'UNREGISTERED') {
            $this->logFailure('token_invalid', $response->status(), $errorCode, $data);

            return PushDeliveryStatus::TokenInvalid;
        }

        $this->logFailure('push_failed', $response->status(), $errorCode, $data);

        return PushDeliveryStatus::Failed;
    }

    /**
     * FCM HTTP v1 message. `notification` carries the hybrid title/body; `data` is string→string, so
     * `ids` is JSON-encoded (the client decodes it). Android high priority so the device wakes for it.
     *
     * @param  array{title: string, body: string}  $notification
     * @param  array{type: string, notification_id: string, ids: array<string, mixed>}  $data
     * @return array<string, mixed>
     */
    private function message(string $token, array $notification, array $data): array
    {
        return [
            'token' => $token,
            'notification' => [
                'title' => (string) ($notification['title'] ?? ''),
                'body' => (string) ($notification['body'] ?? ''),
            ],
            'data' => [
                'type' => (string) ($data['type'] ?? ''),
                'notification_id' => (string) ($data['notification_id'] ?? ''),
                'ids' => json_encode($data['ids'] ?? [], JSON_UNESCAPED_UNICODE) ?: '{}',
            ],
            'android' => ['priority' => 'HIGH'],
        ];
    }

    /** OAuth2 access token from the service account (google/auth), cached under its expiry. */
    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, function (): string {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $this->credentialsPath);

            return (string) ($credentials->fetchAuthToken()['access_token'] ?? '');
        });
    }

    /** Safe failure log — status + FCM error code + notification id only; never token/credential/copy. */
    private function logFailure(string $reason, int $status, string $errorCode, array $data): void
    {
        Log::warning('FCM push not delivered', [
            'reason' => $reason,
            'http_status' => $status,
            'fcm_error' => $errorCode,
            'notification_id' => (string) ($data['notification_id'] ?? ''),
        ]);
    }
}
