<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| Map picker backend (GoMap.az, 2026-10-29): search + pin → address through the server (GUID never reaches the client
| except inside the tile URL), x = longitude / y = latitude mapping, caching, and a GoMap outage answered as 503.
*/

beforeEach(function () {
    config(['integrations.gomap.guid' => 'test-guid', 'integrations.gomap.api_url' => 'https://gomap.test/Main.asmx']);
    Cache::flush();
});

function gomapFake(): void
{
    Http::fake([
        'gomap.test/Main.asmx/searchObj' => Http::response(['success' => true, 'total' => 1, 'rows' => [
            ['x' => 49.8484683, 'y' => 40.3753411, 'nm' => 'Street Bar', 'addr' => 'Nizami 138, Səbail, Bakı', 'stp' => 'Kafe'],
            ['x' => null, 'y' => null, 'nm' => 'broken row'],
        ]]),
        'gomap.test/Main.asmx/getAddressByCoords' => Http::response(['success' => true,
            'addr' => 'Həsən Əliyev 673, Nərimanov, Bakı, Azərbaycan, AZ1072',
            'addr_components' => [
                ['name' => 'Azərbaycan', 'type' => 'country'], ['name' => 'Bakı', 'type' => 'country district'],
                ['name' => 'Nərimanov', 'type' => 'settlement district'], ['name' => 'Həsən Əliyev', 'type' => 'street'],
                ['name' => '673', 'type' => 'house'], ['name' => 'AZ1072', 'type' => 'postal'],
            ]]),
    ]);
}

it('searches places for a signed-in mobile user', function () {
    gomapFake();
    $user = makeUser('+994501230001');

    $this->getJson('/v1/geo/search?q=Nizami&lang=az', bearer(userAccessToken($user)))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Street Bar')
        ->assertJsonPath('data.0.lat', 40.3753411)
        ->assertJsonPath('data.0.lng', 49.8484683);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/searchObj') && $r['guid'] === 'test-guid' && $r['name'] === 'Nizami');
});

it('turns a pin into an address with the house number (x = lng, y = lat), cached', function () {
    gomapFake();
    $admin = makeSuperAdmin();

    foreach ([1, 2] as $_) {
        $this->actingAs($admin, 'admin')->getJson('/admin/v1/geo/reverse?lat=40.404705866746&lng=49.858301281929')
            ->assertOk()
            ->assertJsonPath('data.street', 'Həsən Əliyev')
            ->assertJsonPath('data.house', '673')
            ->assertJsonPath('data.district', 'Nərimanov')
            ->assertJsonPath('data.city', 'Bakı');
    }

    Http::assertSentCount(1); // second answer from the cache
    Http::assertSent(fn ($r) => $r['x'] === '49.858301281929' && $r['y'] === '40.404705866746');
});

it('answers 503 geo_unavailable when GoMap is down', function () {
    Http::fake(['gomap.test/*' => Http::response('', 500)]);

    $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/geo/search?q=Nizami')
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'geo_unavailable');
});

it('hands out the tile URL only to signed-in clients', function () {
    $this->getJson('/v1/geo/tiles')->assertUnauthorized();

    $url = $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/geo/tiles?lang=en')->assertOk()->json('data.url');
    expect($url)->toContain('guid=test-guid')->toContain('lng=en')->toContain('{z}');
});
