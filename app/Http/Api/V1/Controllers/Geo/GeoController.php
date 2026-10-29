<?php

namespace App\Http\Api\V1\Controllers\Geo;

use App\Domain\Geo\GoMapClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Map picker backend (GoMap.az, 2026-10-29) for signed-in clients — mobile (/v1/geo/*, auth:user) and the admin panel
 * (/admin/v1/geo/*, auth:admin). The GoMap GUID stays on the server; clients get results and the tile URL only.
 */
final class GeoController
{
    public function __construct(private readonly GoMapClient $gomap) {}

    /** GET …/geo/search?q=&lang= — places and addresses matching the text (max 10). */
    public function search(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'lang' => ['sometimes', 'string', 'in:az,en,ru'],
        ]);

        return response()->json(['data' => $this->gomap->search($v['q'], $v['lang'] ?? 'az')]);
    }

    /** GET …/geo/reverse?lat=&lng=&lang= — the address at a pin (house number when known). */
    public function reverse(Request $request): JsonResponse
    {
        $v = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lang' => ['sometimes', 'string', 'in:az,en,ru'],
        ]);

        return response()->json(['data' => $this->gomap->reverse((float) $v['lat'], (float) $v['lng'], $v['lang'] ?? 'az')]);
    }

    /** GET …/geo/tiles?lang= — the XYZ tile URL + attribution; null url = not configured (clients fall back to OSM). */
    public function tiles(Request $request): JsonResponse
    {
        $lang = (string) $request->query('lang', 'az');

        return response()->json(['data' => [
            'url' => $this->gomap->configured() ? $this->gomap->tileUrl($lang) : null,
            'attribution' => '© GoMap.az',
            'max_zoom' => 18,
        ]]);
    }
}
