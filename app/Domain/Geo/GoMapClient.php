<?php

namespace App\Domain\Geo;

use App\Domain\Geo\Exceptions\GeoUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GoMap.az API (2026-10-29) — address search and reverse geocoding for the map pickers (mobile application forms,
 * admin device / complex forms). Form-urlencoded POSTs to Main.asmx/<method>, JSON replies. Coordinates are GoMap's
 * x = longitude, y = latitude; everything this class returns uses lat / lng. Results are cached (rounded pin ⇒
 * same address), the GUID never leaves the server, and a GoMap outage becomes GeoUnavailableException (503).
 */
final class GoMapClient
{
    private const LANGS = ['az', 'en', 'ru'];

    /**
     * @return list<array{name: string, address: string, lat: float, lng: float, kind: ?string}>
     */
    public function search(string $query, string $lang): array
    {
        $query = trim($query);
        $lang = $this->lang($lang);
        if (mb_strlen($query) < 2) {
            return [];
        }

        return Cache::remember('geo:search:'.$lang.':'.sha1(mb_strtolower($query)), $this->ttl(), function () use ($query, $lang): array {
            $rows = $this->call('searchObj', ['name' => $query, 'lng' => $lang])['rows'] ?? [];

            $out = [];
            foreach (is_array($rows) ? $rows : [] as $r) {
                if (! is_numeric($r['x'] ?? null) || ! is_numeric($r['y'] ?? null)) {
                    continue;
                }
                $out[] = [
                    'name' => trim((string) ($r['nm'] ?? '')) ?: trim((string) ($r['addr'] ?? '')),
                    'address' => trim((string) ($r['addr'] ?? '')),
                    'lat' => round((float) $r['y'], 7),
                    'lng' => round((float) $r['x'], 7),
                    'kind' => isset($r['stp']) && $r['stp'] !== '' ? (string) $r['stp'] : null,
                ];
            }

            return array_slice($out, 0, 10);
        });
    }

    /**
     * The address at a point (with the house number when GoMap knows it).
     *
     * @return array{address: ?string, street: ?string, house: ?string, district: ?string, city: ?string, postal: ?string}
     */
    public function reverse(float $lat, float $lng, string $lang): array
    {
        $lang = $this->lang($lang);
        // ~1 m: a pin dropped on the same spot reuses the cached answer
        $key = sprintf('geo:rev:%s:%.5f:%.5f', $lang, $lat, $lng);

        return Cache::remember($key, $this->ttl(), function () use ($lat, $lng, $lang): array {
            $res = $this->call('getAddressByCoords', ['x' => (string) $lng, 'y' => (string) $lat, 'lng' => $lang]);
            $parts = [];
            foreach (($res['addr_components'] ?? []) as $c) {
                $name = trim((string) ($c['name'] ?? ''));
                if ($name !== '' && isset($c['type'])) {
                    $parts[(string) $c['type']] = $name;
                }
            }
            $address = trim((string) ($res['addr'] ?? ''));

            return [
                'address' => $address !== '' ? $address : null,
                'street' => $parts['street'] ?? null,
                'house' => $parts['house'] ?? null,
                'district' => $parts['settlement district'] ?? $parts['settlement'] ?? null,
                'city' => $parts['country district'] ?? null,
                'postal' => $parts['postal'] ?? null,
            ];
        });
    }

    /** The XYZ tile URL template for the clients ({z}/{x}/{y} left for the map library to fill). */
    public function tileUrl(string $lang): string
    {
        return strtr((string) config('integrations.gomap.tile_url'), [
            '{guid}' => (string) config('integrations.gomap.guid'),
            '{lang}' => $this->lang($lang),
        ]);
    }

    public function configured(): bool
    {
        return (string) config('integrations.gomap.guid') !== '';
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params): array
    {
        if (! $this->configured()) {
            throw new GeoUnavailableException('not_configured');
        }
        try {
            $response = Http::asForm()
                ->timeout((int) config('integrations.gomap.timeout_seconds', 8))
                ->post(rtrim((string) config('integrations.gomap.api_url'), '/').'/'.$method, $params + ['guid' => (string) config('integrations.gomap.guid')]);
        } catch (Throwable $e) {
            Log::warning('GoMap unreachable', ['method' => $method, 'error' => $e::class]);
            throw new GeoUnavailableException('unreachable');
        }
        $json = $response->json();
        if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
            Log::warning('GoMap error', ['method' => $method, 'status' => $response->status()]);
            throw new GeoUnavailableException('bad_response');
        }

        return $json;
    }

    private function lang(string $lang): string
    {
        return in_array($lang, self::LANGS, true) ? $lang : 'az';
    }

    private function ttl(): int
    {
        return (int) config('integrations.gomap.cache_seconds', 86400);
    }
}
