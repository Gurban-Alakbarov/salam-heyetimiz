<?php

namespace Database\Seeders\Notifications;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Models\NotificationTemplateLocale;
use Illuminate\Database\Seeder;

/**
 * Live notification templates + az/ru/en copy (DB Arch §7.1/§7.2; INVENTORY §1–§2). Idempotent
 * (updateOrCreate) so it is safe to re-run on deploy. `device.opened` (visitor-opened, operational) +
 * the subscription-lifecycle set (billing): expiring D-30/15/7/1, expired, activated, renewed — all fixed
 * push + inapp. No new notification types are introduced here (types come from the frozen enum).
 */
class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        // device.opened — visitor barrier open (INVENTORY §1). Category operational.
        $this->seedTemplate('device.opened', NotificationCategory::Operational, [
            'az' => ['subject' => 'Qonaq daxil oldu', 'body' => '{visitor_name} {device_label} qapısını açdı'],
            'ru' => ['subject' => 'Гость вошёл', 'body' => '{visitor_name} открыл(а) дверь {device_label}'],
            'en' => ['subject' => 'Visitor entered', 'body' => '{visitor_name} opened {device_label}'],
        ]);

        // Subscription lifecycle (INVENTORY §2). Category billing (forced, never user-mutable). Copy is
        // static per threshold/state — no business variables (LOCKED DECISION 1).
        foreach ($this->subscriptionTemplates() as $key => $locales) {
            $this->seedTemplate($key, NotificationCategory::Billing, $locales);
        }
    }

    /**
     * @param  array<string, array{subject: string, body: string}>  $locales
     */
    private function seedTemplate(string $templateKey, NotificationCategory $category, array $locales): void
    {
        $template = NotificationTemplate::query()->updateOrCreate(
            ['template_key' => $templateKey],
            [
                'default_channels_mask' => NotificationChannel::Push->bit() | NotificationChannel::Inapp->bit(),
                'category' => $category,
                'is_user_mutable' => false,
                'is_active' => true,
            ],
        );

        foreach ($locales as $locale => $copy) {
            NotificationTemplateLocale::query()->updateOrCreate(
                ['notification_template_id' => $template->getKey(), 'locale' => $locale],
                ['subject' => $copy['subject'], 'body' => $copy['body']],
            );
        }
    }

    /**
     * @return array<string, array<string, array{subject: string, body: string}>>
     */
    private function subscriptionTemplates(): array
    {
        return [
            'subscription.expiring_30d' => [
                'az' => ['subject' => 'Abunəlik bitmək üzrədir', 'body' => 'Abunəliyinizin bitməsinə 30 gün qalıb'],
                'ru' => ['subject' => 'Подписка скоро истекает', 'body' => 'До окончания вашей подписки осталось 30 дней'],
                'en' => ['subject' => 'Subscription expiring soon', 'body' => 'Your subscription expires in 30 days'],
            ],
            'subscription.expiring_15d' => [
                'az' => ['subject' => 'Abunəlik bitmək üzrədir', 'body' => 'Abunəliyinizin bitməsinə 15 gün qalıb'],
                'ru' => ['subject' => 'Подписка скоро истекает', 'body' => 'До окончания вашей подписки осталось 15 дней'],
                'en' => ['subject' => 'Subscription expiring soon', 'body' => 'Your subscription expires in 15 days'],
            ],
            'subscription.expiring_7d' => [
                'az' => ['subject' => 'Abunəlik bitmək üzrədir', 'body' => 'Abunəliyinizin bitməsinə 7 gün qalıb'],
                'ru' => ['subject' => 'Подписка скоро истекает', 'body' => 'До окончания вашей подписки осталось 7 дней'],
                'en' => ['subject' => 'Subscription expiring soon', 'body' => 'Your subscription expires in 7 days'],
            ],
            'subscription.expiring_1d' => [
                'az' => ['subject' => 'Abunəlik sabah bitir', 'body' => 'Abunəliyiniz sabah başa çatır'],
                'ru' => ['subject' => 'Подписка истекает завтра', 'body' => 'Ваша подписка истекает завтра'],
                'en' => ['subject' => 'Subscription expires tomorrow', 'body' => 'Your subscription expires tomorrow'],
            ],
            'subscription.expired' => [
                'az' => ['subject' => 'Abunəlik başa çatdı', 'body' => 'Abunəliyiniz başa çatıb'],
                'ru' => ['subject' => 'Подписка истекла', 'body' => 'Ваша подписка истекла'],
                'en' => ['subject' => 'Subscription expired', 'body' => 'Your subscription has expired'],
            ],
            'subscription.activated' => [
                'az' => ['subject' => 'Abunəlik aktivləşdirildi', 'body' => 'Abunəliyiniz aktivləşdirildi'],
                'ru' => ['subject' => 'Подписка активирована', 'body' => 'Ваша подписка активирована'],
                'en' => ['subject' => 'Subscription activated', 'body' => 'Your subscription has been activated'],
            ],
            'subscription.renewed' => [
                'az' => ['subject' => 'Abunəlik yeniləndi', 'body' => 'Abunəliyiniz yeniləndi'],
                'ru' => ['subject' => 'Подписка продлена', 'body' => 'Ваша подписка продлена'],
                'en' => ['subject' => 'Subscription renewed', 'body' => 'Your subscription has been renewed'],
            ],
        ];
    }
}
