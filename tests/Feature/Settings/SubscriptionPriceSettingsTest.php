<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Subscriptions\Support\SubscriptionPriceResolver;

/*
| Ayarlar › Abunəliklər — the subscription price (per tier) and term are admin settings, live for every new
| subscription and paid renewal; config/domain/subscriptions.php is only the fallback.
*/

it('defaults to the config price and term (15 AZN / 365 days) while nothing is saved', function () {
    $r = app(SubscriptionPriceResolver::class);

    expect($r->commercial(SubscriptionTier::Main))->toMatchArray(['price_minor' => 1500, 'term_days' => 365])
        ->and($r->commercial(SubscriptionTier::Additional))->toMatchArray(['price_minor' => 1500, 'term_days' => 365]);
});

it('an admin price change applies at once to new subscriptions, renewals and the app price', function () {
    seedRbac();
    $super = makeSuperAdmin();

    $this->actingAs($super, 'admin')->getJson('/admin/v1/settings')->assertOk()
        ->assertJsonFragment(['key' => 'price_main_minor', 'type' => 'money', 'min' => 100, 'max' => 100000]);

    $this->actingAs($super, 'admin')
        ->patchJson('/admin/v1/settings/subscriptions', ['price_main_minor' => 1800, 'price_additional_minor' => 900, 'subscription_duration_days' => 180])
        ->assertOk()
        ->assertJsonPath('values.price_main_minor', 1800)
        ->assertJsonPath('values.subscription_duration_days', 180);

    $me = makeUser('+994500770001');
    $sub = app(SubscriptionService::class)->createPending(makeDeviceUser($me, makeActiveDevice('SER-PRC-1', '+994700770001')), SubscriptionTier::Main);
    expect($sub->price_minor)->toBe(1800)->and($sub->term_days)->toBe(180);

    $old = makeSubscription(makeDeviceUser($me, makeActiveDevice('SER-PRC-2', '+994700770002')), ['price_minor' => 1500, 'term_days' => 365]);
    $this->actingAs($me, 'user')->postJson("/v1/subscriptions/{$old->id}/renew", [], ['Idempotency-Key' => 'prc-renew'])
        ->assertOk()->assertJsonPath('amount_minor', 1800);

    $this->actingAs($me, 'user')->getJson('/v1/subscriptions')->assertOk()
        ->assertJsonPath('data.0.renewal_price_minor', 1800)
        ->assertJsonPath('data.0.renewal_term_days', 180);
    expect(app(SubscriptionPriceResolver::class)->commercial(SubscriptionTier::Additional)['price_minor'])->toBe(900);

    $change = collect(AuditLog::query()->where('action', 'settings.updated')->latest('id')->first()->payload['changes'])
        ->firstWhere('key', 'price_main_minor');
    expect($change)->toMatchArray(['old' => '15.00 AZN', 'new' => '18.00 AZN']);
});

it('refuses a zero price or an out-of-range term and saves nothing', function () {
    seedRbac();
    $super = makeSuperAdmin();

    $this->actingAs($super, 'admin')
        ->patchJson('/admin/v1/settings/subscriptions', ['price_main_minor' => 0, 'subscription_duration_days' => 365])
        ->assertStatus(422)->assertJsonPath('error.code', 'invalid_setting')->assertJsonPath('error.fields', ['price_main_minor']);

    $this->actingAs($super, 'admin')
        ->patchJson('/admin/v1/settings/subscriptions', ['subscription_duration_days' => 5000])
        ->assertStatus(422)->assertJsonPath('error.fields', ['subscription_duration_days']);

    $this->actingAs($super, 'admin')
        ->patchJson('/admin/v1/settings/subscriptions', ['price_main_minor' => 'abc'])
        ->assertStatus(422);

    expect(app(SubscriptionPriceResolver::class)->commercial(SubscriptionTier::Main))->toMatchArray(['price_minor' => 1500, 'term_days' => 365]);
});
