<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\SettingsVersion;
use App\Domain\Admin\Settings\SettingsCatalog;
use Illuminate\Http\Request;

/**
 * Append-only version history for settings (R-DOM-09). Every mutation snapshots the full map; History,
 * Compare and Restore read from here. Secrets are redacted in any human-facing diff (never the ciphertext).
 */
final class SettingsVersionService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array<int, array<string, mixed>>  $changes  already-redacted [{key, old, new}]
     */
    public function record(string $reason, ?string $scopeGroup, array $changes, Request $request): SettingsVersion
    {
        $actor = $request->user('admin');

        return SettingsVersion::query()->create([
            'reason' => $reason,
            'scope_group' => $scopeGroup,
            'snapshot' => $this->settings->rawAll(),
            'changes' => $changes !== [] ? $changes : null,
            'created_by_admin_id' => $actor?->getKey(),
            'actor_label' => $actor?->getAttribute('email'),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    /** @return array<int, array<string, mixed>> recent versions (metadata only, no full snapshot) */
    public function list(int $limit = 50): array
    {
        return SettingsVersion::query()->latest('id')->limit($limit)->get()
            ->map(fn (SettingsVersion $v): array => $this->meta($v))->all();
    }

    /**
     * @return array{from: array<string,mixed>, to: array<string,mixed>, diff: array<int, array<string,mixed>>}
     */
    public function compare(int $fromId, int $toId): array
    {
        $a = SettingsVersion::query()->findOrFail($fromId);
        $b = SettingsVersion::query()->findOrFail($toId);
        $keys = array_unique([...array_keys($a->snapshot), ...array_keys($b->snapshot)]);
        sort($keys);

        $diff = [];
        foreach ($keys as $key) {
            $av = $a->snapshot[$key] ?? null;
            $bv = $b->snapshot[$key] ?? null;
            if ($av !== $bv) {
                $diff[] = ['key' => $key, 'from' => $this->redact($key, $av), 'to' => $this->redact($key, $bv)];
            }
        }

        return ['from' => $this->meta($a), 'to' => $this->meta($b), 'diff' => $diff];
    }

    public function restore(int $id, Request $request): SettingsVersion
    {
        $version = SettingsVersion::query()->findOrFail($id);
        $this->settings->restoreRaw($version->snapshot, $request->user('admin')?->getKey());

        return $this->record('restore', null, [['key' => '*', 'old' => 'previous', 'new' => "version#$id"]], $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(SettingsVersion $v): array
    {
        return [
            'id' => $v->id,
            'reason' => $v->reason,
            'scope_group' => $v->scope_group,
            'changes' => $v->changes,
            'actor_label' => $v->actor_label,
            'ip' => $v->ip,
            'created_at' => $v->created_at?->toIso8601String(),
        ];
    }

    private function redact(string $fullKey, mixed $value): mixed
    {
        [$group, $key] = array_pad(explode('.', $fullKey, 2), 2, '');
        if ($value !== null && $value !== '' && $this->settings->isSecret($group, $key)) {
            return '••••••';
        }

        return $value;
    }
}
