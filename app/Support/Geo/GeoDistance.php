<?php

namespace App\Support\Geo;

/**
 * Great-circle distance (Haversine) between two WGS-84 points, in metres. Server-side ONLY
 * (GEOFENCE-1 D2 — a client-provided distance/radius is never trusted). Earth radius 6 371 000 m.
 */
final class GeoDistance
{
    private const EARTH_RADIUS_M = 6_371_000.0;

    public static function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * asin(min(1.0, sqrt($a)));
    }
}
