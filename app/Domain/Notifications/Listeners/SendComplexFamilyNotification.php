<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Admin\Models\Complex;
use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Notifications\Services\TemplateRenderer;
use App\Domain\Roster\Events\ComplexFamilyChanged;
use App\Domain\Users\Models\User;
use App\Support\Enums\Locale;
use App\Support\Time\Clock;

/**
 * O7 — the Komendant changed a family: everyone concerned hears it (push + inapp), each in their own language.
 *  - member_removed → `family.member_removed` to the removed member and to the head;
 *  - head_changed   → `family.head_changed` to the new head, the other members and the previous head.
 */
final class SendComplexFamilyNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly TemplateRenderer $renderer,
        private readonly Clock $clock,
    ) {}

    public function handle(ComplexFamilyChanged $event): void
    {
        $complexName = (string) Complex::query()->whereKey($event->complexId)->value('name');
        $stamp = $this->clock->now()->format('YmdHis');

        if ($event->kind === ComplexFamilyChanged::MEMBER_REMOVED) {
            $vars = ['member_name' => $this->name($event->subjectUserId), 'complex_name' => $complexName];
            foreach (array_unique([$event->subjectUserId, $event->headUserId]) as $userId) {
                $this->send($userId, NotificationType::FamilyMemberRemoved, 'family.member_removed', $vars,
                    "family_member_removed:{$event->complexId}:{$event->subjectUserId}:{$stamp}", $event);
            }

            return;
        }

        $vars = ['head_name' => $this->name($event->headUserId), 'complex_name' => $complexName];
        $recipients = array_unique(array_filter([$event->headUserId, ...$event->memberUserIds, $event->previousHeadUserId]));
        foreach ($recipients as $userId) {
            $this->send((int) $userId, NotificationType::FamilyHeadChanged, 'family.head_changed', $vars,
                "family_head_changed:{$event->complexId}:{$event->headUserId}:{$stamp}", $event);
        }
    }

    /** @param  array<string, string>  $vars */
    private function send(int $userId, NotificationType $type, string $templateKey, array $vars, string $dedupeKey, ComplexFamilyChanged $event): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }

        $rendered = $this->renderer->render($templateKey, $user->preferred_language ?? Locale::default(), $vars);
        if ($rendered === null) {
            return;
        }

        $this->dispatcher->dispatch(new NotificationRequest(
            userId: $userId,
            type: $type,
            templateKey: $templateKey,
            title: $rendered['title'],
            body: $rendered['body'],
            ids: ['complex_id' => $event->complexId, 'user_id' => $event->subjectUserId],
            channels: [NotificationChannel::Push, NotificationChannel::Inapp],
            dedupeKey: $dedupeKey,
        ));
    }

    private function name(int $userId): string
    {
        $user = User::query()->find($userId, ['id', 'full_name', 'email']);

        return trim((string) ($user?->full_name ?: $user?->email ?: ''));
    }
}
