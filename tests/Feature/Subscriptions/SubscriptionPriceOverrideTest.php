<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\SubscriptionPriceOverride;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Subscriptions\Support\SubscriptionPriceResolver;

/*
| Special subscription prices: person → device → complex → the standard price.
*/

beforeEach(fn () => seedRbac());

function specialPrice(string $scope, int $subjectId, int $priceMinor): SubscriptionPriceOverride
{
    return SubscriptionPriceOverride::query()->create(['scope' => $scope, 'subject_id' => $subjectId, 'price_minor' => $priceMinor]);
}

/** @return array{0: \App\Domain\Users\Models\User, 1: \App\Domain\Devices\Models\Device, 2: DeviceUser} */
function pricedMember(?int $complexId = null): array
{
    $user = makeUser('+99450'.random_int(1000000, 9999999));
    $device = makeActiveDevice('SER-'.uniqid(), '+99470'.random_int(1000000, 9999999));
    $device->update(['complex_id' => $complexId, 'owner_user_id' => $user->id]);

    return [$user, $device, makeDeviceUser($user, $device)];
}

function setPrice(array $body, $actor = null): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($actor ?? makeSuperAdmin('price'.uniqid().'@salam.az'), 'admin')->putJson('/admin/v1/subscription-prices', $body);
}

it('uses the standard price without a special one', function () {
    [, , $du] = pricedMember();

    expect(app(SubscriptionPriceResolver::class)->forDeviceUser(SubscriptionTier::Main, $du)['price_minor'])->toBe(1500);
});

it('prefers person, then device, then complex', function () {
    $complex = makeComplex();
    [$user, $device, $du] = pricedMember((int) $complex->id);
    $resolver = app(SubscriptionPriceResolver::class);
    $price = fn (): int => $resolver->forDeviceUser(SubscriptionTier::Main, $du)['price_minor'];

    specialPrice('complex', (int) $complex->id, 1000);
    expect($price())->toBe(1000);

    specialPrice('device', (int) $device->id, 1800);
    expect($price())->toBe(1800);

    specialPrice('user', (int) $user->id, 2000);
    expect($price())->toBe(2000);
});

it('applies a person\'s price to all their gates and to nobody else', function () {
    [$user, , $du] = pricedMember();
    $secondGate = makeActiveDevice('SER-SECOND', '+994700000777');
    $secondRow = makeDeviceUser($user, $secondGate);
    [, , $otherPerson] = pricedMember();
    specialPrice('user', (int) $user->id, 2000);
    $resolver = app(SubscriptionPriceResolver::class);

    expect($resolver->forDeviceUser(SubscriptionTier::Main, $du)['price_minor'])->toBe(2000)
        ->and($resolver->forDeviceUser(SubscriptionTier::Main, $secondRow)['price_minor'])->toBe(2000)
        ->and($resolver->forDeviceUser(SubscriptionTier::Main, $otherPerson)['price_minor'])->toBe(1500);
});

it('covers both tiers and keeps the term', function () {
    [$user, , $du] = pricedMember();
    specialPrice('user', (int) $user->id, 2000);

    $terms = app(SubscriptionPriceResolver::class)->forDeviceUser(SubscriptionTier::Additional, $du);

    expect($terms['price_minor'])->toBe(2000)->and($terms['term_days'])->toBe(365);
});

it('snapshots the special price on a new subscription', function () {
    [$user, , $du] = pricedMember();
    specialPrice('user', (int) $user->id, 2000);

    expect(app(SubscriptionService::class)->createPending($du, SubscriptionTier::Main)->price_minor)->toBe(2000);
});

it('charges a special price set after the subscription started waiting for payment', function () {
    [$user, , $du] = pricedMember();
    $subscription = app(SubscriptionService::class)->createPending($du, SubscriptionTier::Main);
    specialPrice('user', (int) $user->id, 2000);

    expect(app(SubscriptionPriceResolver::class)->chargeMinorFor($subscription->refresh(), OrderItemType::SubMain))->toBe(2000);
});

it('keeps the snapshot of a waiting subscription when there is no special price', function () {
    [, , $du] = pricedMember();
    $subscription = makeSubscription($du, ['status' => SubscriptionStatus::PendingPayment, 'price_minor' => 1200]);

    expect(app(SubscriptionPriceResolver::class)->chargeMinorFor($subscription, OrderItemType::SubMain))->toBe(1200);
});

it('creates the order with the special price', function () {
    [$user, , $du] = pricedMember();
    $subscription = app(SubscriptionService::class)->createPending($du, SubscriptionTier::Main);
    specialPrice('user', (int) $user->id, 2000);

    $this->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_main',
        'items' => [['item_type' => 'sub_main', 'referenced_id' => $subscription->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'special-1'])->assertStatus(201)->assertJsonPath('amount_minor', 2000);
});

it('leaves an active subscription as it is and takes the new price at renewal', function () {
    [$user, , $du] = pricedMember();
    $subscription = makeSubscription($du, ['price_minor' => 1500]);
    specialPrice('user', (int) $user->id, 2000);

    expect($subscription->refresh()->price_minor)->toBe(1500)
        ->and(app(SubscriptionPriceResolver::class)->chargeMinorFor($subscription, OrderItemType::SubRenewal))->toBe(2000);
});

it('shows the person their renewal price in the app', function () {
    [$user, , $du] = pricedMember();
    makeSubscription($du, ['price_minor' => 1500]);
    specialPrice('user', (int) $user->id, 2000);

    $this->actingAs($user, 'user')->getJson('/v1/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.0.renewal_price_minor', 2000)
        ->assertJsonPath('data.0.price_minor', 1500);
});

it('lets a super admin set, list and remove a price, audited', function () {
    [$user] = pricedMember();
    $admin = makeSuperAdmin('prices@salam.az');

    setPrice(['scope' => 'user', 'subject_id' => $user->id, 'price_minor' => 2000], $admin)->assertOk();
    setPrice(['scope' => 'user', 'subject_id' => $user->id, 'price_minor' => 2500], $admin)->assertOk();

    expect(SubscriptionPriceOverride::query()->count())->toBe(1);
    $audit = AuditLog::query()->where('action', 'subscription.price_set')->latest('id')->firstOrFail();
    expect((string) json_encode($audit->getAttributes()))->toContain('2000')->toContain('2500');

    $list = $this->actingAs($admin, 'admin')->getJson('/admin/v1/subscription-prices')->assertOk();
    $list->assertJsonPath('data.0.price_minor', 2500)->assertJsonPath('data.0.scope', 'user');

    $this->actingAs($admin, 'admin')->deleteJson('/admin/v1/subscription-prices/'.$list->json('data.0.id'))->assertNoContent();

    expect(SubscriptionPriceOverride::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'subscription.price_removed')->count())->toBe(1);
});

it('lets finance set a price but not a komendant or support', function () {
    [$user] = pricedMember();
    $complex = makeComplex('CX-P', 'P');

    setPrice(['scope' => 'user', 'subject_id' => $user->id, 'price_minor' => 2000], makeAdminRole('finance'))->assertOk();
    setPrice(['scope' => 'user', 'subject_id' => $user->id, 'price_minor' => 2100], makeAdminRole('support'))->assertForbidden();
    setPrice(['scope' => 'complex', 'subject_id' => $complex->id, 'price_minor' => 2100], makeAdminRole('complex_manager', (int) $complex->id))->assertForbidden();

    $this->actingAs(makeAdminRole('complex_manager', (int) $complex->id, 'km2@salam.az'), 'admin')
        ->getJson('/admin/v1/subscription-prices')->assertForbidden();
});

it('refuses 0 AZN, a price outside the limits and an unknown subject', function (array $body) {
    pricedMember();
    setPrice($body)->assertStatus(422);
})->with([
    '0 AZN' => [['scope' => 'device', 'subject_id' => 1, 'price_minor' => 0]],
    'too low' => [['scope' => 'device', 'subject_id' => 1, 'price_minor' => 99]],
    'too high' => [['scope' => 'device', 'subject_id' => 1, 'price_minor' => 100001]],
    'unknown scope' => [['scope' => 'planet', 'subject_id' => 1, 'price_minor' => 2000]],
    'unknown subject' => [['scope' => 'user', 'subject_id' => 99999999, 'price_minor' => 2000]],
]);
