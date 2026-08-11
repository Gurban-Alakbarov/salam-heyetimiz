<?php

namespace App\Http\Api\V1\Controllers;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Notifications\Queries\NotificationQuery;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Domain\Users\Models\User;
use App\Http\Api\V1\Support\RespondsWithEnvelope;
use App\Http\Resources\UserSelfResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One-shot app bootstrap (so the Flutter client never has to fan out a second/third request on launch):
 *   - GET /v1/bootstrap  (guest)         — app config a logged-out client needs to start safely.
 *   - GET /v1/me         (auth:user)     — the current user PLUS everything the authed app needs.
 *
 * Both responses use the unified envelope. The `/v1/me` `data` object is intentionally EXTENSIBLE — future
 * sections (apartments, vehicles, devices, complexes, notifications, invitations, visitor_passes,
 * family_members, payments) are added as NEW keys without changing the existing contract. Placeholder keys
 * (permissions, unread_notifications_count, avatar) are present now so clients can bind them today.
 */
class BootstrapController
{
    use RespondsWithEnvelope;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly SubscriptionQuery $subscriptions,
        private readonly NotificationQuery $notifications,
    ) {}

    /** GET /v1/bootstrap — guest app configuration (public). */
    public function guest(): JsonResponse
    {
        return $this->success([
            'app' => $this->appBlock(),
            'otp' => [
                'length' => (int) $this->settings->value('otp', 'otp_length'),
                'ttl_seconds' => (int) $this->settings->value('otp', 'otp_ttl_seconds'),
                'resend_seconds' => (int) $this->settings->value('otp', 'otp_resend_seconds'),
            ],
            'public_settings' => [
                'brand' => $this->str('general', 'app_name') ?: 'Salam Həyətimiz',
                'default_locale' => $this->str('general', 'default_language') ?: 'az',
                'default_timezone' => $this->str('general', 'timezone') ?: 'Asia/Baku',
                'currency' => 'AZN',
                'logo_url' => $this->str('general', 'logo_url'),
            ],
            'feature_flags' => $this->featureFlags(),
            'support' => [
                'email' => $this->str('general', 'support_email'),
                'phone' => $this->str('general', 'support_phone'),
            ],
        ], 'Konfiqurasiya hazırdır.');
    }

    /** GET /v1/me — current user + authed bootstrap (auth:user). */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->has_active_subscription = $this->subscriptions->hasActiveForUser((int) $user->getKey());

        return $this->success([
            'user' => (new UserSelfResource($user))->toArray($request),
            'registration_completed' => $user->email_verified_at !== null,
            'email_verified' => $user->email_verified_at !== null,
            'phone' => $user->phone,
            'has_password' => $user->password !== null,
            'avatar' => null,                       // reserved (no avatar field yet — future)
            'locale' => $user->preferred_language->value,
            'timezone' => $this->str('general', 'timezone') ?: 'Asia/Baku',
            'app' => $this->appBlock(),
            'feature_flags' => $this->featureFlags(),
            'permissions' => [],                    // mobile users have no RBAC (reserved/extensible)
            'unread_notifications_count' => $this->notifications->unreadCount((int) $user->getKey()),
            'user_devices' => $this->userDevices($user),
            'active_subscriptions' => $this->activeSubscriptions((int) $user->getKey()),
            // Future sections (apartments, vehicles, devices, complexes, notifications, invitations,
            // visitor_passes, family_members, payments) are added here as new keys — no contract change.
        ], 'Profil hazırdır.');
    }

    /** @return array<string, mixed> */
    private function appBlock(): array
    {
        return [
            'maintenance_mode' => (bool) $this->settings->value('general', 'maintenance_mode'),
            'min_version' => $this->str('general', 'min_version') ?: '1.0.0',
            'latest_version' => $this->str('general', 'latest_version') ?: '1.0.0',
            'force_update' => (bool) $this->settings->value('general', 'force_update'),
        ];
    }

    /** @return array<string, bool> */
    private function featureFlags(): array
    {
        return [
            'email_otp' => (bool) $this->settings->value('otp', 'enable_email_otp'),
            'sms_login' => false,                   // SMS login retired (infra preserved, not used)
            'registration' => true,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function userDevices(User $user): array
    {
        return $user->userDevices()->whereNull('revoked_at')->orderByDesc('last_seen_at')->get()
            ->map(static fn ($d): array => [
                'id' => (int) $d->id,
                'platform' => $d->platform->value,
                'device_model' => $d->device_model,
                'app_version' => $d->app_version,
                'os_version' => $d->os_version,
                'last_seen_at' => optional($d->last_seen_at)->toIso8601String(),
                'biometric_enrolled' => (bool) $d->biometric_enrolled,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function activeSubscriptions(int $userId): array
    {
        return $this->subscriptions->forUser($userId, 'active', 25, null)['data']
            ->map(static fn ($s): array => [
                'id' => (int) $s->id,
                'tier' => $s->tier->value,
                'status' => $s->status->value,
                'starts_at' => optional($s->starts_at)->toIso8601String(),
                'ends_at' => optional($s->ends_at)->toIso8601String(),
                'auto_renew' => (bool) $s->auto_renew,
            ])->all();
    }

    private function str(string $group, string $key): string
    {
        return (string) $this->settings->value($group, $key);
    }
}
