<?php

namespace Database\Seeders\Notifications;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Models\NotificationTemplateLocale;
use Illuminate\Database\Seeder;

/**
 * Live notification templates + az/ru/en copy (DB Arch §7.1/§7.2; INVENTORY §1). Idempotent
 * (updateOrCreate) so it is safe to re-run on deploy. MVP: `device.opened` — the visitor-opened
 * variant, fixed push + inapp, category operational. No new notification types are introduced here.
 */
class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $template = NotificationTemplate::query()->updateOrCreate(
            ['template_key' => 'device.opened'],
            [
                'default_channels_mask' => NotificationChannel::Push->bit() | NotificationChannel::Inapp->bit(),
                'category' => NotificationCategory::Operational,
                'is_user_mutable' => false,
                'is_active' => true,
            ],
        );

        $locales = [
            'az' => ['subject' => 'Qonaq daxil oldu', 'body' => '{visitor_name} {device_label} qapısını açdı'],
            'ru' => ['subject' => 'Гость вошёл', 'body' => '{visitor_name} открыл(а) дверь {device_label}'],
            'en' => ['subject' => 'Visitor entered', 'body' => '{visitor_name} opened {device_label}'],
        ];

        foreach ($locales as $locale => $copy) {
            NotificationTemplateLocale::query()->updateOrCreate(
                ['notification_template_id' => $template->getKey(), 'locale' => $locale],
                ['subject' => $copy['subject'], 'body' => $copy['body']],
            );
        }
    }
}
