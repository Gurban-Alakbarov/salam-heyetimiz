<?php

use App\Domain\Visitor\Enums\VisitorAccessType;

/*
| The public /v/{token} page (no login). Renders a self-contained mobile page: barrier + inviter, an open
| button for live links, a Directions link when the barrier has coordinates. Dead links render a plain
| message and never expose the open control.
*/

it('renders the open page for a live link', function () {
    $owner = makeUser('+994509300001');
    $device = makeOwnedDevice($owner, 'VP-1', '+994700930001');
    $device->update(['location_label' => 'Blok A qapısı', 'latitude' => 40.4093, 'longitude' => 49.8671]);
    [, $token] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60, 'Qonaq');

    $res = $this->get("/v/{$token}")
        ->assertOk()
        ->assertSee('Blok A qapısı')
        ->assertSee(__('visitor.open_barrier'))
        ->assertSee(__('visitor.directions'))
        // Barrier coordinates flow into the client payload (power the directions targets).
        ->assertSee('40.4093')
        ->assertSee('49.8671')
        // Android branch: AzNav via intent://, with the Play Store only as the in-intent fallback.
        ->assertSee('net.sinam.aznav')
        ->assertSee('intent://')
        ->assertSee('browser_fallback_url')
        // iOS / non-Android branch: Apple Maps directions to the coordinates (never the Play Store).
        ->assertSee('maps.apple.com/?daddr=')
        ->assertSee('dirflg=d')
        ->assertSee('Apple Maps')
        // Google Maps/Waze/etc. stay removed — AzNav (Android) + Apple Maps (iOS) are the only targets.
        ->assertDontSee('google.com/maps/dir');

    // Regression guard for the iOS bug: the non-Android branch opens Apple Maps, never the Play
    // Store — the old `: AZNAV_PLAY` ternary that pointed iOS at play.google.com must be gone.
    expect($res->getContent())->not->toContain(': AZNAV_PLAY');
});

it('renders a 404 page for an unknown token and hides the open control', function () {
    $this->get('/v/totally-unknown-token')
        ->assertStatus(404)
        ->assertSee(__('visitor.not_found'))
        ->assertDontSee(__('visitor.open_barrier'));
});

it('renders a dead link message without the open control', function () {
    $owner = makeUser('+994509300002');
    $device = makeOwnedDevice($owner, 'VP-2', '+994700930002');
    [$link, $token] = mintVisitorLink($device, $owner);
    $link->forceFill(['revoked_at' => now()])->save();

    $this->get("/v/{$token}")
        ->assertOk()
        ->assertSee(__('visitor.revoked'))
        ->assertDontSee(__('visitor.open_barrier'));
});

it('does not offer directions when the barrier has no coordinates', function () {
    $owner = makeUser('+994509300003');
    $device = makeOwnedDevice($owner, 'VP-3', '+994700930003'); // no lat/lng
    [, $token] = mintVisitorLink($device, $owner);

    $this->get("/v/{$token}")
        ->assertOk()
        ->assertDontSee(__('visitor.directions'));
});
