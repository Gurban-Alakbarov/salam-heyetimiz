<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Exceptions\NotificationNotMutableException;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Models\UserNotificationSetting;

/**
 * O13 — a user's notification switches. Only templates flagged `is_user_mutable` can be switched off (decided
 * 2026-10-27: the visitor-opened notice `device.opened` and admin announcements `system.admin_campaign`; billing
 * reminders stay forced). Switching one off stops its PUSH only — the in-app inbox row is still written, so
 * nothing is lost. Stored in user_notification_settings (template_key, channel=push, enabled).
 */
final class NotificationPreferences
{
    /** @var array<string, bool> template_key → is_user_mutable, per process */
    private array $mutable = [];

    /** True when this user switched the push of this template off (a forced template is never muted). */
    public function pushMuted(int $userId, ?string $templateKey): bool
    {
        if ($templateKey === null || ! $this->isMutable($templateKey)) {
            return false;
        }

        return UserNotificationSetting::query()
            ->where('user_id', $userId)
            ->where('template_key', $templateKey)
            ->where('channel', NotificationChannel::Push->value)
            ->where('enabled', false)
            ->exists();
    }

    /** @return list<array{template_key: string, push_enabled: bool}> every switchable template, with this user's state */
    public function forUser(int $userId): array
    {
        $muted = UserNotificationSetting::query()
            ->where('user_id', $userId)
            ->where('channel', NotificationChannel::Push->value)
            ->where('enabled', false)
            ->pluck('template_key')
            ->all();

        return NotificationTemplate::query()
            ->where('is_user_mutable', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('template_key')
            ->map(fn (string $key): array => ['template_key' => $key, 'push_enabled' => ! in_array($key, $muted, true)])
            ->values()
            ->all();
    }

    public function setPush(int $userId, string $templateKey, bool $enabled): void
    {
        if (! $this->isMutable($templateKey)) {
            throw new NotificationNotMutableException($templateKey);
        }

        UserNotificationSetting::query()->updateOrCreate(
            ['user_id' => $userId, 'template_key' => $templateKey, 'channel' => NotificationChannel::Push->value],
            ['enabled' => $enabled],
        );
    }

    private function isMutable(string $templateKey): bool
    {
        return $this->mutable[$templateKey] ??= (bool) NotificationTemplate::query()
            ->where('template_key', $templateKey)
            ->value('is_user_mutable');
    }
}
