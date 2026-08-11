<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\Devices\Services\DeviceLookup;
use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Notifications\Services\TemplateRenderer;
use App\Domain\Users\Models\User;
use App\Domain\Visitor\Models\VisitorLink;
use App\Support\Enums\Locale;
use Illuminate\Support\Facades\Log;

/**
 * First business notification (INVENTORY §1 MVP #1): a confirmed visitor barrier open notifies the
 * link's owning resident. Consumes the shared [OpenCommandCompleted] — the SAME signal the Visitor
 * usage listener uses — and fires ONLY on a Visitor-source command that reached `Opened` (observed
 * actuation, R-GSM-03; never on `dispatched`). Recipient = the link creator, falling back to the device
 * owner; with neither it is a safe no-op. Renders `device.opened` from DB templates in the recipient's
 * locale and dispatches push + inapp via [NotificationDispatcher] (dedupe `visitor_opened:{command_id}`).
 * Synchronous, mirroring IncrementVisitorUsageOnOpenCompleted; the push itself is queued downstream.
 * Reads cross-module context via [DeviceLookup] (R-ARCH-06) plus the trigger VisitorLink + the recipient
 * User; it changes no DeviceComm/Visitor/OpenCommand logic.
 */
final class SendVisitorOpenedNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly TemplateRenderer $renderer,
        private readonly DeviceLookup $devices,
    ) {}

    public function handle(OpenCommandCompleted $event): void
    {
        $command = $event->command;

        // Confirmed visitor open only — never on dispatched (INVENTORY §1).
        if ($command->source !== OpenCommandSource::Visitor || $command->state !== OpenCommandState::Opened) {
            return;
        }

        $linkId = data_get($command->metadata, 'visitor_link_id');
        if (! is_numeric($linkId)) {
            return;
        }

        $link = VisitorLink::query()->find((int) $linkId);
        if ($link === null) {
            return;
        }

        $deviceId = (int) $command->device_id;
        $recipientId = $link->created_by_user_id ?? $this->devices->ownerUserId($deviceId);
        if ($recipientId === null) {
            Log::info('visitor-opened notification skipped: no recipient', ['command_id' => $command->getKey()]);

            return;
        }

        /** @var User|null $recipient */
        $recipient = User::query()->find($recipientId);
        if ($recipient === null) {
            Log::info('visitor-opened notification skipped: recipient missing', ['command_id' => $command->getKey()]);

            return;
        }

        $locale = $recipient->preferred_language ?? Locale::default();
        $rendered = $this->renderer->render('device.opened', $locale, [
            'visitor_name' => $link->visitor_name ?? '',
            'device_label' => $this->devices->locationLabel($deviceId) ?? '',
        ]);

        if ($rendered === null) {
            Log::warning('visitor-opened notification skipped: template unresolved', ['command_id' => $command->getKey()]);

            return;
        }

        $this->dispatcher->dispatch(new NotificationRequest(
            userId: (int) $recipientId,
            type: NotificationType::VisitorLinkUsed,
            templateKey: 'device.opened',
            title: $rendered['title'],
            body: $rendered['body'],
            ids: ['visitor_link_id' => (int) $linkId, 'device_id' => $deviceId],
            channels: [NotificationChannel::Push, NotificationChannel::Inapp],
            dedupeKey: 'visitor_opened:'.$command->getKey(),
        ));
    }
}
