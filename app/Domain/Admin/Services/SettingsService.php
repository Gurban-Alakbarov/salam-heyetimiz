<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\Settings\SettingsCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Runtime settings store (R-DOM-09): DB-backed key/value, grouped by the SettingsCatalog. Every setting is
 * editable from the panel with no code change or deploy. The full key→value map is cached and busted on
 * write (immediate effect). Secrets are stored **encrypted** and never returned in clear to the API.
 *
 * Backward-compatible: the original get()/bool()/set() + REQUIRE_2FA keep working (security.require_2fa).
 */
final class SettingsService
{
    public const REQUIRE_2FA = 'security.require_2fa';

    private const CACHE_KEY = 'settings:map';

    /** Raw stored value for a key (from cache); default if absent. Secrets return the ENCRYPTED blob. */
    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->map()[$key] ?? null;

        return $value ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');

        return $value === '1' || $value === 'true';
    }

    public function set(string $key, string $value, ?int $updatedByAdminId = null): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by_admin_id' => $updatedByAdminId]);
        $this->bust();
    }

    /** Typed value for a catalog setting (secrets decrypted) — for internal consumers. */
    public function value(string $group, string $key): mixed
    {
        $def = SettingsCatalog::definition($group, $key);
        if ($def === null) {
            return $this->get("$group.$key");
        }

        return $this->coerceOut("$group.$key", $def);
    }

    /**
     * Typed values for a whole group (secrets decrypted) — internal use.
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $out = [];
        foreach (SettingsCatalog::groups()[$group] ?? [] as $key => $def) {
            $out[$key] = $this->coerceOut("$group.$key", $def);
        }

        return $out;
    }

    /**
     * API view of a group: typed values, but secrets are MASKED (returned as '' with a `_secrets` flag map).
     *
     * @return array{values: array<string, mixed>, secrets_set: array<string, bool>}
     */
    public function groupForApi(string $group): array
    {
        $values = [];
        $secretsSet = [];
        foreach (SettingsCatalog::groups()[$group] ?? [] as $key => $def) {
            if (($def['type'] ?? '') === 'secret') {
                $raw = $this->map()["$group.$key"] ?? null;
                $secretsSet[$key] = $raw !== null && $raw !== '';
                $values[$key] = ''; // never expose the secret
            } else {
                $values[$key] = $this->coerceOut("$group.$key", $def);
            }
        }

        return ['values' => $values, 'secrets_set' => $secretsSet];
    }

    /**
     * Persist a group's values (coerced per catalog; secrets encrypted; a blank secret KEEPS the existing
     * one). Unknown keys are ignored. Busts the cache so changes take effect immediately.
     *
     * @param  array<string, mixed>  $values
     */
    public function setGroup(string $group, array $values, ?int $adminId = null): void
    {
        $defs = SettingsCatalog::groups()[$group] ?? [];
        foreach ($values as $key => $value) {
            $def = $defs[$key] ?? null;
            if ($def === null) {
                continue;
            }
            $type = $def['type'];

            if ($type === 'secret') {
                if ($value === null || $value === '') {
                    continue; // blank → keep existing secret
                }
                $stored = Crypt::encryptString((string) $value);
            } elseif ($type === 'bool') {
                $stored = ($value === true || $value === '1' || $value === 1 || $value === 'true') ? '1' : '0';
            } elseif ($type === 'int') {
                $stored = (string) (int) $value;
            } else {
                $stored = (string) $value;
            }

            Setting::query()->updateOrCreate(['key' => "$group.$key"], ['value' => $stored, 'updated_by_admin_id' => $adminId]);
        }
        $this->bust();
    }

    /**
     * The full stored key→value map (raw; secrets as ciphertext) — used for version snapshots and export.
     *
     * @return array<string, string>
     */
    public function rawAll(): array
    {
        return $this->map();
    }

    /**
     * Write raw stored values back verbatim (used by version restore + import). Only known catalog keys are
     * applied. Secrets restore as their ciphertext (same APP_KEY → still decryptable). Busts the cache.
     *
     * @param  array<string, mixed>  $map
     */
    public function restoreRaw(array $map, ?int $adminId = null): void
    {
        $known = [];
        foreach (SettingsCatalog::groups() as $group => $defs) {
            foreach ($defs as $key => $_) {
                $known["$group.$key"] = true;
            }
        }
        foreach ($map as $key => $value) {
            if (! isset($known[$key]) || $value === null) {
                continue;
            }
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value, 'updated_by_admin_id' => $adminId]);
        }
        $this->bust();
    }

    /** Is this catalog "group.key" a secret? */
    public function isSecret(string $group, string $key): bool
    {
        return (SettingsCatalog::definition($group, $key)['type'] ?? '') === 'secret';
    }

    /** Seed any missing catalog defaults (idempotent — never overwrites an existing value). */
    public function seedDefaults(): void
    {
        foreach (SettingsCatalog::groups() as $group => $defs) {
            foreach ($defs as $key => $def) {
                if (($def['type'] ?? '') === 'secret') {
                    continue; // never seed secrets
                }
                $fullKey = "$group.$key";
                if (! Setting::query()->where('key', $fullKey)->exists()) {
                    $default = $def['default'];
                    $stored = $def['type'] === 'bool' ? ($default ? '1' : '0') : (string) $default;
                    Setting::query()->create(['key' => $fullKey, 'value' => $stored]);
                }
            }
        }
        $this->bust();
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function coerceOut(string $fullKey, array $def): mixed
    {
        $raw = $this->map()[$fullKey] ?? null;
        $type = $def['type'] ?? 'string';

        if ($raw === null) {
            return $type === 'bool' ? (bool) ($def['default'] ?? false) : ($type === 'int' ? (int) ($def['default'] ?? 0) : ($def['default'] ?? ''));
        }

        return match ($type) {
            'bool' => $raw === '1' || $raw === 'true',
            'int' => (int) $raw,
            'secret' => $this->tryDecrypt($raw),
            default => $raw,
        };
    }

    private function tryDecrypt(string $raw): string
    {
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $raw; // legacy plaintext (pre-encryption)
        }
    }

    /**
     * @return array<string, string>
     */
    private function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, static fn (): array => Setting::query()->pluck('value', 'key')->all());
    }

    private function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
