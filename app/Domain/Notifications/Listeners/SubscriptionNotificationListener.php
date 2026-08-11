<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Notifications\Services\TemplateRenderer;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Support\Enums\Locale;
use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing for the subscription-lifecycle notifications (INVENTORY §2; Phase 4A). Each concrete
 * listener consumes ONE existing Subscriptions event — reused unchanged (R-NOT-21) — and maps it to a
 * DB-backed template + LOCKED dedupe key, dispatched as push + inapp (category billing) to the
 * entitlement owner.
 *
 * Recipient = subscription.device_user_id → device_users.user_id → users.id (LOCKED DECISION 3); no
 * owner/admin fallback. Copy is static per template (no business variables — DECISION 1), rendered in the
 * recipient's locale. A missing recipient or unresolved template is a safe no-op. Content resolution +
 * fan-out stay in [TemplateRenderer] / [NotificationDispatcher]; these listeners add no business rule to
 * the Subscriptions module and change no dispatch site (R-ARCH-06).
 */
abstract class SubscriptionNotificationListener
{
    public function __construct(
        protected readonly NotificationDispatcher $dispatcher,
        protected readonly TemplateRenderer $renderer,
    ) {}

    /**
     * Render the LOCKED template for the subscription owner and dispatch push + inapp. No-op when the
     * subscription has no resolvable recipient user or the template cannot be resolved.
     */
    protected function notify(Subscription $subscription, NotificationType $type, string $templateKey, string $dedupeKey): void
    {
        $deviceUser = $subscription->deviceUser;
        if ($deviceUser === null || $deviceUser->user_id === null) {
            Log::info('subscription notification skipped: no recipient', [
                'subscription_id' => $subscription->getKey(),
                'template_key' => $templateKey,
            ]);

            return;
        }

        /** @var User|null $recipient */
        $recipient = User::query()->find($deviceUser->user_id);
        if ($recipient === null) {
            Log::info('subscription notification skipped: recipient missing', [
                'subscription_id' => $subscription->getKey(),
                'template_key' => $templateKey,
            ]);

            return;
        }

        $locale = $recipient->preferred_language ?? Locale::default();

        // Subscription copy is static per threshold/state — no business variables (DECISION 1).
        $rendered = $this->renderer->render($templateKey, $locale, []);
        if ($rendered === null) {
            Log::warning('subscription notification skipped: template unresolved', [
                'subscription_id' => $subscription->getKey(),
                'template_key' => $templateKey,
            ]);

            return;
        }

        $this->dispatcher->dispatch(new NotificationRequest(
            userId: (int) $recipient->getKey(),
            type: $type,
            templateKey: $templateKey,
            title: $rendered['title'],
            body: $rendered['body'],
            ids: [
                'subscription_id' => (int) $subscription->getKey(),
                'device_id' => (int) $deviceUser->device_id,
            ],
            channels: [NotificationChannel::Push, NotificationChannel::Inapp],
            dedupeKey: $dedupeKey,
        ));
    }
}
