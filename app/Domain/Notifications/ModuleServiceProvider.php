<?php

namespace App\Domain\Notifications;

use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\Notifications\Adapters\FakePushClient;
use App\Domain\Notifications\Adapters\FcmPushClient;
use App\Domain\Notifications\Contracts\PushClient;
use App\Domain\Notifications\Listeners\SendSubscriptionActivatedNotification;
use App\Domain\Notifications\Listeners\SendSubscriptionExpiredNotification;
use App\Domain\Notifications\Listeners\SendSubscriptionExpiringNotification;
use App\Domain\Notifications\Listeners\SendSubscriptionRenewedNotification;
use App\Domain\Notifications\Listeners\SendVisitorOpenedNotification;
use App\Domain\Subscriptions\Events\SubscriptionActivated;
use App\Domain\Subscriptions\Events\SubscriptionExpired;
use App\Domain\Subscriptions\Events\SubscriptionExpiringSoon;
use App\Domain\Subscriptions\Events\SubscriptionRenewed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Notifications (Backend Arch §14.9): template-driven, multi-channel (push/sms/inapp/
 * email), idempotent fan-out. Major event consumer; content lives in DB (R-LOC-03).
 *
 * Binds the provider-agnostic [PushClient] (R-ARCH-08): the real FCM HTTP v1 transport
 * ([FcmPushClient]) in provisioned non-testing environments, and the deterministic [FakePushClient]
 * in testing, when FCM_DRIVER=fake, or when no service-account credential is present — so tests and
 * un-provisioned environments never attempt a real send. iOS delivery stays behind this same interface
 * until Apple/Firebase iOS credentials land; only the credential + native config differ, not the seam.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PushClient::class, function ($app): PushClient {
            $path = $this->fcmCredentialsPath();

            if ($app->environment('testing') || config('integrations.fcm.driver') === 'fake' || $path === null) {
                return new FakePushClient;
            }

            return new FcmPushClient(
                projectId: (string) config('integrations.fcm.project_id'),
                credentialsPath: $path,
                timeoutSeconds: (int) config('integrations.fcm.timeout_seconds', 10),
            );
        });
    }

    public function boot(): void
    {
        // First business notification (INVENTORY §1 MVP #1): a confirmed visitor barrier open notifies
        // the link's owning resident. Additive consumer of the existing event — DeviceComm/Visitor
        // logic is untouched, and IncrementVisitorUsageOnOpenCompleted keeps its own subscription.
        Event::listen(OpenCommandCompleted::class, SendVisitorOpenedNotification::class);

        // Subscription lifecycle notifications (INVENTORY §2; Phase 4A): additive consumers of the
        // existing Subscriptions events — no dispatch site or Subscriptions logic is changed (R-ARCH-06).
        // Each maps to a DB template + LOCKED dedupe; SubscriptionActivated notifies only a real paid
        // activation (the comp-grant path has no billing period and is a no-op).
        Event::listen(SubscriptionExpiringSoon::class, SendSubscriptionExpiringNotification::class);
        Event::listen(SubscriptionExpired::class, SendSubscriptionExpiredNotification::class);
        Event::listen(SubscriptionActivated::class, SendSubscriptionActivatedNotification::class);
        Event::listen(SubscriptionRenewed::class, SendSubscriptionRenewedNotification::class);
    }

    /** Absolute path to the service-account JSON, or null when unset/missing (→ FakePushClient). */
    private function fcmCredentialsPath(): ?string
    {
        $path = config('integrations.fcm.credentials_path');

        if (blank($path)) {
            return null;
        }

        // Resolve a repo-relative path (e.g. storage/keys/…) against the app base; leave absolute as-is.
        if (! str_starts_with($path, '/') && ! preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            $path = base_path($path);
        }

        return is_file($path) ? $path : null;
    }
}
