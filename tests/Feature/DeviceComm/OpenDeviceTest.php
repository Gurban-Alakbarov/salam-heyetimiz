<?php

use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Queue;

/*
| POST /v1/devices/{id}/open — openDevice (R-DOM-05 permission, cooldown, idempotency, queue).
*/

it('issues a queued open command and enqueues dispatch', function () {
    Queue::fake();
    $user = makeUser('+994509000001');
    $device = makeOpenableDevice($user, 'SER-OP1', '+994700090001');

    $response = $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'open-1']);

    $response->assertStatus(202)
        ->assertJsonPath('state', 'queued')
        ->assertJsonPath('driver_confirms_actuation', true) // traccar confirms actuation (v1.2)
        ->assertJsonPath('websocket_channel', "private-user.{$user->id}")
        ->assertJsonStructure(['command_id', 'state', 'expected_completion_ms', 'driver_confirms_actuation', 'websocket_channel']);

    expect((int) $response->json('expected_completion_ms'))->toBe(3000); // traccar default, <10 samples
    expect(OpenCommand::query()->where('device_id', $device->id)->where('state', 'queued')->count())->toBe(1);

    Queue::assertPushed(DispatchOpenCommandJob::class);
});

it('issues a CLOSE command through the same endpoint when direction=close (Phase 5)', function () {
    Queue::fake();
    $user = makeUser('+994509000010');
    $device = makeOpenableDevice($user, 'SER-OP8', '+994700090008');

    $response = $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", ['direction' => 'close'], ['Idempotency-Key' => 'close-1']);

    $response->assertStatus(202)->assertJsonPath('state', 'queued');

    $command = OpenCommand::query()->where('device_id', $device->id)->latest('id')->firstOrFail();
    expect($command->direction->value)->toBe('close')
        ->and($command->source->value)->toBe('mobile');

    Queue::assertPushed(DispatchOpenCommandJob::class);
});

it('defaults to the open direction when none is given', function () {
    Queue::fake();
    $user = makeUser('+994509000011');
    $device = makeOpenableDevice($user, 'SER-OP9', '+994700090009');

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'opendir-1'])
        ->assertStatus(202);

    $command = OpenCommand::query()->where('device_id', $device->id)->latest('id')->firstOrFail();
    expect($command->direction->value)->toBe('open');
});

it('rejects an invalid direction with 422', function () {
    $user = makeUser('+994509000012');
    $device = makeOpenableDevice($user, 'SER-OP10', '+994700090010');

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", ['direction' => 'toggle'], ['Idempotency-Key' => 'bad-dir'])
        ->assertStatus(422);
});

it('is idempotent on the Idempotency-Key', function () {
    Queue::fake();
    $user = makeUser('+994509000002');
    $device = makeOpenableDevice($user, 'SER-OP2', '+994700090002');

    $first = $this->actingAs($user, 'user')->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'same']);
    $second = $this->actingAs($user, 'user')->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'same']);

    expect($first->json('command_id'))->toBe($second->json('command_id'))
        ->and(OpenCommand::query()->where('device_id', $device->id)->count())->toBe(1);
});

it('requires an Idempotency-Key', function () {
    $user = makeUser('+994509000003');
    $device = makeOpenableDevice($user, 'SER-OP3', '+994700090003');

    $this->actingAs($user, 'user')->postJson("/v1/devices/{$device->id}/open", [])->assertStatus(422);
});

it('enforces the cooldown on a rapid second open', function () {
    Queue::fake();
    $user = makeUser('+994509000004');
    $device = makeOpenableDevice($user, 'SER-OP4', '+994700090004');

    $this->actingAs($user, 'user')->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'cd-1'])->assertStatus(202);

    $this->actingAs($user, 'user')->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'cd-2'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'cooldown')
        ->assertHeader('Retry-After');
});

it('refuses to open a disabled device with device_disabled', function () {
    Queue::fake();
    $user = makeUser('+994509000005');
    $device = makeOpenableDevice($user, 'SER-OP5', '+994700090005');
    $device->update(['status' => DeviceStatus::Disabled->value]);

    $this->actingAs($user, 'user')->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'dis-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'device_disabled');
});

it('refuses to open for a lapsed member while others stay active (subscription_required)', function () {
    Queue::fake();
    $owner = makeUser('+994509000006');
    $member = makeUser('+994509000007');
    $device = makeOpenableDevice($owner, 'SER-OP6', '+994700090006'); // owner has active sub
    $memberDu = makeDeviceUser($member, $device, 'user');
    makeSubscription($memberDu, ['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

    $this->actingAs($member, 'user')->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'sub-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'subscription_required');
});

it('hides a device the caller is not on behind a 404', function () {
    Queue::fake();
    $owner = makeUser('+994509000008');
    $stranger = makeUser('+994509000009');
    $device = makeOpenableDevice($owner, 'SER-OP7', '+994700090007');

    $this->actingAs($stranger, 'user')->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'x'])
        ->assertStatus(404);
});

it('requires authentication', function () {
    $this->postJson('/v1/devices/1/open', [], ['Idempotency-Key' => 'k'])->assertStatus(401);
});
