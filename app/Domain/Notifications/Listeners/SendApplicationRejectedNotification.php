<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Applications\Events\ApplicationRejected;
use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Notifications\Services\TemplateRenderer;
use App\Domain\Users\Models\User;
use App\Support\Enums\Locale;

/**
 * A8 — a rejected applicant (legal entity or individual) is told in the app too (push + inapp, in their own
 * language), next to the rejection email. Dedupe per application.
 */
final class SendApplicationRejectedNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function handle(ApplicationRejected $event): void
    {
        $user = User::query()->find($event->userId);
        if ($user === null) {
            return;
        }

        $rendered = $this->renderer->render('application.rejected', $user->preferred_language ?? Locale::default(), [
            'reason' => $event->reason,
        ]);
        if ($rendered === null) {
            return;
        }

        $this->dispatcher->dispatch(new NotificationRequest(
            userId: $event->userId,
            type: NotificationType::ApplicationRejected,
            templateKey: 'application.rejected',
            title: $rendered['title'],
            body: $rendered['body'],
            ids: ['application_id' => $event->applicationId, 'kind' => $event->kind],
            channels: [NotificationChannel::Push, NotificationChannel::Inapp],
            dedupeKey: "application_rejected:{$event->kind}:{$event->applicationId}",
        ));
    }
}
