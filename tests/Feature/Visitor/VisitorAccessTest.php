<?php

use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Models\VisitorLink;
use App\Domain\Visitor\Models\VisitorLinkUsage;
use Illuminate\Support\Facades\Queue;

/*
| Public visitor access — the no-login /v1/visit/{token} API behind the shared page. The token is the only
| credential; opening reuses the exact mobile relay pipeline. Every payload is anonymous-safe (no ids) and
| every attempt is audited. usage_count advances only on a CONFIRMED open, never on the attempt itself.
*/

/** Terminal visitor command row (source=visitor, link attributed via metadata) for lifecycle assertions. */
function visitorCommand(Device $device, VisitorLink $link, string $state): OpenCommand
{
    $now = now();

    return OpenCommand::query()->create([
        'partition_key' => (int) $now->format('Ym'),
        'device_id' => $device->getKey(),
        'driver' => $device->driver_type->value,
        'state' => $state,
        'attempts' => 1,
        'requested_at' => $now,
        'completed_at' => $now,
        'source' => OpenCommandSource::Visitor->value,
        'metadata' => ['visitor_link_id' => (int) $link->getKey()],
        'created_at' => $now,
    ]);
}

it('returns an anonymous-safe status for a live link', function () {
    $owner = makeUser('+994509200001');
    $device = makeOwnedDevice($owner, 'VA-ST1', '+994700920001');
    $device->update(['location_label' => 'Blok A qapısı']);
    [, $token] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60, 'Çatdırılma');

    $res = $this->getJson("/v1/visit/{$token}")->assertOk();

    $res->assertJsonPath('barrier_name', 'Blok A qapısı')
        ->assertJsonPath('visitor_name', 'Çatdırılma')
        ->assertJsonPath('access_type', 'time_limited')
        ->assertJsonPath('valid', true)
        ->assertJsonPath('reason', null);

    // No internal identifiers ever leak to the anonymous caller.
    expect($res->json())->not->toHaveKey('id')
        ->and($res->json())->not->toHaveKey('device_id')
        ->and($res->json())->not->toHaveKey('link_id')
        ->and($res->json())->not->toHaveKey('token');
});

it('returns 404 for an unknown token without revealing anything', function () {
    $this->getJson('/v1/visit/does-not-exist-token')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'visitor_link_not_found');
});

it('exposes raw coordinates (not a maps URL) for a live link with a located barrier', function () {
    $owner = makeUser('+994509200020');
    $device = makeOwnedDevice($owner, 'VA-CO1', '+994700920020');
    $device->update(['latitude' => 40.4093, 'longitude' => 49.8671]);
    [, $token] = mintVisitorLink($device, $owner);

    $res = $this->getJson("/v1/visit/{$token}")->assertOk();
    expect($res->json('coordinates.lat'))->toBe(40.4093)
        ->and($res->json('coordinates.lng'))->toBe(49.8671);
});

it('returns null coordinates when the barrier has none', function () {
    $owner = makeUser('+994509200021');
    $device = makeOwnedDevice($owner, 'VA-CO2', '+994700920021'); // no lat/lng
    [, $token] = mintVisitorLink($device, $owner);

    $this->getJson("/v1/visit/{$token}")->assertOk()->assertJsonPath('coordinates', null);
});

it('hides coordinates once the link is no longer valid', function () {
    $owner = makeUser('+994509200022');
    $device = makeOwnedDevice($owner, 'VA-CO3', '+994700920022');
    $device->update(['latitude' => 40.4, 'longitude' => 49.8]);
    [$link, $token] = mintVisitorLink($device, $owner);
    $link->forceFill(['revoked_at' => now()])->save();

    $this->getJson("/v1/visit/{$token}")->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('coordinates', null);
});

it('opens the barrier through the shared pipeline and audits the attempt', function () {
    Queue::fake();
    $owner = makeUser('+994509200002');
    $device = makeOwnedDevice($owner, 'VA-OP1', '+994700920002');
    [$link, $token] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60);

    $res = $this->postJson("/v1/visit/{$token}/open")
        ->assertStatus(202)
        ->assertJsonStructure(['command_id', 'state', 'expected_completion_ms', 'driver_confirms_actuation']);

    Queue::assertPushed(DispatchOpenCommandJob::class);

    $command = OpenCommand::query()->latest('id')->firstOrFail();
    expect($command->source)->toBe(OpenCommandSource::Visitor)
        ->and((int) $command->device_id)->toBe((int) $device->id)
        ->and($command->user_id)->toBeNull()
        ->and((int) data_get($command->metadata, 'visitor_link_id'))->toBe((int) $link->id);

    // The attempt is audited as accepted, but usage is NOT yet spent (only a confirmed open spends it).
    expect(VisitorLinkUsage::query()->where('visitor_link_id', $link->id)->where('result', 'accepted')->count())->toBe(1)
        ->and((int) $link->refresh()->usage_count)->toBe(0);
});

it('refuses to open a revoked link', function () {
    Queue::fake();
    $owner = makeUser('+994509200003');
    $device = makeOwnedDevice($owner, 'VA-OP2', '+994700920003');
    [$link, $token] = mintVisitorLink($device, $owner);
    $link->forceFill(['revoked_at' => now()])->save();

    $this->postJson("/v1/visit/{$token}/open")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'visitor_link_revoked');

    Queue::assertNothingPushed();
    expect(VisitorLinkUsage::query()->where('visitor_link_id', $link->id)->where('result', 'revoked')->count())->toBe(1);
});

it('refuses to open an expired link with 410', function () {
    Queue::fake();
    $owner = makeUser('+994509200004');
    $device = makeOwnedDevice($owner, 'VA-OP3', '+994700920004');
    [$link, $token] = mintVisitorLink($device, $owner);
    $link->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->postJson("/v1/visit/{$token}/open")
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'visitor_link_expired');

    Queue::assertNothingPushed();
});

it('refuses to open a one-time link that is already used up', function () {
    Queue::fake();
    $owner = makeUser('+994509200005');
    $device = makeOwnedDevice($owner, 'VA-OP4', '+994700920005');
    [$link, $token] = mintVisitorLink($device, $owner, VisitorAccessType::OneTime, null);
    $link->forceFill(['usage_count' => 1])->save(); // max_usage is 1 for one_time

    $this->postJson("/v1/visit/{$token}/open")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'visitor_link_usage_exceeded');

    Queue::assertNothingPushed();
});

it('refuses to open when the device is not active', function () {
    Queue::fake();
    $owner = makeUser('+994509200006');
    $device = makeOwnedDevice($owner, 'VA-OP5', '+994700920006');
    [, $token] = mintVisitorLink($device, $owner);
    $device->update(['status' => DeviceStatus::Disabled->value]);

    $this->postJson("/v1/visit/{$token}/open")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'visitor_device_inactive');

    Queue::assertNothingPushed();
});

it('polls a command only within the same link scope', function () {
    Queue::fake();
    $owner = makeUser('+994509200007');
    $device = makeOwnedDevice($owner, 'VA-PL1', '+994700920007');
    [$link, $token] = mintVisitorLink($device, $owner);
    [$otherLink, $otherToken] = mintVisitorLink($device, $owner);

    $commandId = $this->postJson("/v1/visit/{$token}/open")->assertStatus(202)->json('command_id');

    // Correct token → visible.
    $this->getJson("/v1/visit/{$token}/command/{$commandId}")
        ->assertOk()
        ->assertJsonPath('command_id', $commandId);

    // A different link's token cannot poll this command.
    $this->getJson("/v1/visit/{$otherToken}/command/{$commandId}")->assertStatus(404);
});

it('spends one use only when the open is confirmed successful', function () {
    $owner = makeUser('+994509200008');
    $device = makeOwnedDevice($owner, 'VA-US1', '+994700920008');
    [$link] = mintVisitorLink($device, $owner);

    OpenCommandCompleted::dispatch(visitorCommand($device, $link, OpenCommandState::Opened->value));

    expect((int) $link->refresh()->usage_count)->toBe(1)
        ->and($link->refresh()->last_used_at)->not->toBeNull();
});

it('does not spend a use when the open fails', function () {
    $owner = makeUser('+994509200009');
    $device = makeOwnedDevice($owner, 'VA-US2', '+994700920009');
    [$link] = mintVisitorLink($device, $owner);

    OpenCommandCompleted::dispatch(visitorCommand($device, $link, OpenCommandState::Failed->value));

    expect((int) $link->refresh()->usage_count)->toBe(0);
});

it('marks a used-up one-time link invalid on the status endpoint', function () {
    $owner = makeUser('+994509200010');
    $device = makeOwnedDevice($owner, 'VA-US3', '+994700920010');
    [$link, $token] = mintVisitorLink($device, $owner, VisitorAccessType::OneTime, null);
    $link->forceFill(['usage_count' => 1])->save();

    $this->getJson("/v1/visit/{$token}")
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('reason', 'usage_exceeded');
});
