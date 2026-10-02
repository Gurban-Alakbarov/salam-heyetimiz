<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Models\NotificationTemplateLocale;
use App\Domain\Subscriptions\Events\SubscriptionExpired;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;
use Illuminate\Support\Facades\Bus;

/*
| IMPLEMENTATION_PLAN B10 — notification template editor: subject/body per az/en/ru for the existing
| templates, placeholder whitelist, audit, preview; the dispatcher picks up saved copy; re-seeding never
| overwrites admin edits; system.admin_campaign read-only.
*/

beforeEach(function () {
    (new NotificationTemplatesSeeder)->run();
    $this->super = makeSuperAdmin();
    $this->tpl = fn (string $key) => NotificationTemplate::query()->where('template_key', $key)->firstOrFail();
});

it('lists templates with locale completeness, placeholders and the read-only campaign wrapper', function () {
    $data = collect($this->actingAs($this->super, 'admin')->getJson('/admin/v1/notification-templates')->assertOk()->json('data'))->keyBy('template_key');

    expect($data)->toHaveCount(9)
        ->and($data['device.opened']['placeholders'])->toBe(['visitor_name', 'device_label'])
        ->and($data['device.opened']['locales_complete'])->toBeTrue()
        ->and($data['subscription.expired']['placeholders'])->toBe([])
        ->and($data['system.admin_campaign']['read_only'])->toBeTrue();

    $show = $this->actingAs($this->super, 'admin')->getJson('/admin/v1/notification-templates/'.($this->tpl)('device.opened')->id)->assertOk();
    expect(collect($show->json('data.locales'))->pluck('locale')->all())->toBe(['az', 'ru', 'en'])
        ->and($show->json('data.locales.0.body'))->toBe('{visitor_name} {device_label} qapısını açdı');
});

it('edits subject/body per locale; audit keeps old and new; the dispatcher uses the new copy', function () {
    Bus::fake();
    $tpl = ($this->tpl)('subscription.expired');

    $this->actingAs($this->super, 'admin')->putJson("/admin/v1/notification-templates/{$tpl->id}/locales/az", [
        'subject' => 'Abunəliyin vaxtı bitdi', 'body' => 'Yeniləmək üçün tətbiqə daxil olun',
    ])->assertOk()->assertJsonPath('data.updated_by_admin_id', $this->super->id);

    $audit = AuditLog::query()->where('action', 'notification.template_updated')->firstOrFail();
    expect($audit->payload['old'])->toBe(['subject' => 'Abunəlik başa çatdı', 'body' => 'Abunəliyiniz başa çatıb'])
        ->and($audit->payload['new']['subject'])->toBe('Abunəliyin vaxtı bitdi')
        ->and($audit->payload['locale'])->toBe('az');

    $user = makeUser('+994551010001');
    $sub = makeSubscription(makeDeviceUser($user, makeActiveDevice('SER-B10-1', '+994700100001'), 'owner'));
    SubscriptionExpired::dispatch($sub);
    $push = Notification::query()->where('user_id', $user->id)->where('channel', NotificationChannel::Push->value)->firstOrFail();
    expect($push->payload['title'])->toBe('Abunəliyin vaxtı bitdi')->and($push->payload['body'])->toBe('Yeniləmək üçün tətbiqə daxil olun');

    // en / ru untouched
    expect(NotificationTemplateLocale::query()->where('notification_template_id', $tpl->id)->where('locale', 'en')->value('body'))->toBe('Your subscription has expired');
});

it('rejects unknown placeholders (422) and accepts whitelisted ones', function () {
    $opened = ($this->tpl)('device.opened');
    $expired = ($this->tpl)('subscription.expired');
    $put = fn ($t, array $b) => $this->actingAs($this->super, 'admin')->putJson("/admin/v1/notification-templates/{$t->id}/locales/en", $b);

    $put($opened, ['subject' => 'Visitor', 'body' => '{visitor_name} at {device_label} — {amount}'])->assertStatus(422);
    $put($expired, ['subject' => 'Hi {name}', 'body' => 'Expired'])->assertStatus(422);       // static template: no variables at all
    $put($opened, ['subject' => 'Visitor at {device_label}', 'body' => '{visitor_name} came in'])->assertOk();
    $put($opened, ['subject' => str_repeat('a', 121), 'body' => 'x'])->assertStatus(422);
    $put($opened, ['subject' => 'x', 'body' => str_repeat('b', 1001)])->assertStatus(422);
    $put($opened, ['subject' => 'x', 'body' => ''])->assertStatus(422);
    $this->actingAs($this->super, 'admin')->putJson("/admin/v1/notification-templates/{$opened->id}/locales/de", ['subject' => 'x', 'body' => 'y'])->assertNotFound();

    expect(AuditLog::query()->where('action', 'notification.template_updated')->count())->toBe(1);
});

it('previews a draft or the stored copy with sample variables, saving nothing', function () {
    $opened = ($this->tpl)('device.opened');
    $as = $this->actingAs($this->super, 'admin');

    $as->postJson("/admin/v1/notification-templates/{$opened->id}/preview", ['locale' => 'az'])->assertOk()
        ->assertJsonPath('data.body', 'Kuryer Əsas darvaza qapısını açdı');
    $as->postJson("/admin/v1/notification-templates/{$opened->id}/preview", ['locale' => 'en', 'subject' => 'Hey', 'body' => '{visitor_name} is here'])->assertOk()
        ->assertJsonPath('data.title', 'Hey')->assertJsonPath('data.body', 'Kuryer is here');
    $as->postJson("/admin/v1/notification-templates/{$opened->id}/preview", ['body' => '{secret}'])->assertStatus(422);

    expect(NotificationTemplateLocale::query()->where('notification_template_id', $opened->id)->where('locale', 'en')->value('body'))->toBe('{visitor_name} opened {device_label}')
        ->and(AuditLog::query()->where('action', 'notification.template_updated')->count())->toBe(0);
});

it('keeps system.admin_campaign read-only', function () {
    $campaign = ($this->tpl)('system.admin_campaign');
    $this->actingAs($this->super, 'admin')->putJson("/admin/v1/notification-templates/{$campaign->id}/locales/az", ['subject' => 'x', 'body' => 'y'])
        ->assertStatus(409)->assertJsonPath('error.code', 'template_read_only');
    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/notification-templates/{$campaign->id}/preview", [])->assertStatus(409);
    expect(NotificationTemplateLocale::query()->where('notification_template_id', $campaign->id)->count())->toBe(0);
});

it('permissions: operator/support view only; manage is super-admin; finance/complex_manager denied', function () {
    $this->getJson('/admin/v1/notification-templates')->assertStatus(401);
    $tpl = ($this->tpl)('subscription.renewed');
    $body = ['subject' => 'x', 'body' => 'y'];

    foreach (['operator', 'support'] as $role) {
        $admin = makeAdminRole($role);
        $this->actingAs($admin, 'admin')->getJson('/admin/v1/notification-templates')->assertOk();
        $this->actingAs($admin, 'admin')->postJson("/admin/v1/notification-templates/{$tpl->id}/preview", [])->assertOk();
        $this->actingAs($admin, 'admin')->putJson("/admin/v1/notification-templates/{$tpl->id}/locales/az", $body)->assertForbidden();
    }
    foreach (['finance', 'complex_manager'] as $role) {
        $this->actingAs(makeAdminRole($role, $role === 'complex_manager' ? makeComplex('CX-B10', 'B10')->id : null), 'admin')
            ->getJson('/admin/v1/notification-templates')->assertForbidden();
    }
});

it('re-seeding never overwrites admin-edited copy but still refreshes untouched rows', function () {
    $tpl = ($this->tpl)('subscription.activated');
    $this->actingAs($this->super, 'admin')->putJson("/admin/v1/notification-templates/{$tpl->id}/locales/ru", ['subject' => 'Готово', 'body' => 'Подписка включена'])->assertOk();
    NotificationTemplateLocale::query()->where('notification_template_id', $tpl->id)->where('locale', 'en')->update(['body' => 'stale']); // not admin-edited

    (new NotificationTemplatesSeeder)->run();

    $rows = NotificationTemplateLocale::query()->where('notification_template_id', $tpl->id)->get()->keyBy('locale');
    expect($rows['ru']->body)->toBe('Подписка включена')
        ->and($rows['en']->body)->toBe('Your subscription has been activated')
        ->and($rows)->toHaveCount(3);
});
