<?php

use App\Support\Geo\GeoDistance;

/*
| GEOFENCE-1 (D2) — server-side Haversine. Analytic reference values (R = 6 371 000 m):
| one degree of latitude ≈ 111 194.9 m; one degree of longitude scales by cos(latitude).
*/

it('is zero for identical points', function () {
    expect(GeoDistance::metersBetween(40.0, 49.0, 40.0, 49.0))->toEqualWithDelta(0.0, 1e-6);
});

it('measures one degree of latitude', function () {
    expect(GeoDistance::metersBetween(0.0, 0.0, 1.0, 0.0))->toEqualWithDelta(111194.9, 5.0);
});

it('measures one degree of longitude at the equator', function () {
    expect(GeoDistance::metersBetween(0.0, 0.0, 0.0, 1.0))->toEqualWithDelta(111194.9, 5.0);
});

it('scales longitude by the cosine of the latitude', function () {
    // One degree of longitude at 60° N ≈ 111 194.9 · cos(60°) ≈ 55 596 m.
    expect(GeoDistance::metersBetween(60.0, 0.0, 60.0, 1.0))->toEqualWithDelta(55596.0, 30.0);
});

it('is symmetric', function () {
    $ab = GeoDistance::metersBetween(40.1, 49.1, 41.2, 48.3);
    $ba = GeoDistance::metersBetween(41.2, 48.3, 40.1, 49.1);

    expect($ab)->toBe($ba)->and($ab)->toBeGreaterThan(0.0);
});

it('matches a known city-pair great-circle distance', function () {
    // London (51.5074, -0.1278) → Paris (48.8566, 2.3522): great-circle ≈ 343.6 km.
    expect(GeoDistance::metersBetween(51.5074, -0.1278, 48.8566, 2.3522))
        ->toEqualWithDelta(343600.0, 5000.0);
});

it('returns short sub-radius distances for nearby points', function () {
    // ~0.004° of longitude at 40° N ≈ 341 m (used by the open-flow geofence tests).
    expect(GeoDistance::metersBetween(40.0, 49.0, 40.0, 49.004))
        ->toEqualWithDelta(340.7, 5.0);
});
