<?php

namespace App\Http\Admin\V1\Controllers\Settings;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Services\SettingsVersionService;
use App\Domain\Admin\Settings\SettingsCatalog;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payments\Exceptions\PaymentSettingsInvalidException;
use App\Domain\Payments\Services\PaymentSettingsValidator;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * System Settings (system.settings.manage — super_admin tier). DB-backed, grouped, cached, secrets encrypted.
 * Every mutation records a rich audit entry (old→new + actor + ip + ua) AND an immutable version snapshot for
 * History / Compare / Restore. Import/Export support disaster recovery + server migration (JSON).
 */
class SettingsController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly SettingsVersionService $versions,
        private readonly AuditLogger $audit,
        private readonly PaymentSettingsValidator $paymentValidator,
    ) {}

    /** GET /admin/v1/settings — adminGetSettings. Full grouped catalog + values (secrets masked). */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);

        $groups = [];
        foreach (SettingsCatalog::groups() as $group => $defs) {
            $api = $this->settings->groupForApi($group);
            $fields = [];
            foreach ($defs as $key => $def) {
                $fields[] = ['key' => $key, 'type' => $def['type'], 'label' => $def['label'], 'options' => $def['options'] ?? null];
            }
            $groups[] = ['group' => $group, 'fields' => $fields, 'values' => $api['values'], 'secrets_set' => $api['secrets_set']];
        }

        return response()->json([
            'groups' => $groups,
            'readonly' => [
                'payments' => [
                    'webhook_url' => url('/v1/payments/webhook'),
                    'return_url' => url('/v1/payments/return'),
                ],
                'traccar' => [
                    'webhook_url' => url('/v1/traccar/forward'),
                ],
            ],
        ]);
    }

    /** PATCH /admin/v1/settings/{group} — adminUpdateSettings. Diff → rich audit + version snapshot. */
    public function updateGroup(Request $request, string $group): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        abort_unless(in_array($group, SettingsCatalog::groupNames(), true), 404);

        $input = (array) $request->except(['_token']);

        // Payments: when the gateway is being enabled, validate (format + live OAuth) BEFORE persisting.
        // On failure nothing is saved and the exact upstream reason is returned (never a generic error).
        if ($group === 'payments') {
            $effective = $this->effectivePayments($input);
            if (! empty($effective['kapital_enabled'])) {
                try {
                    $this->paymentValidator->validate($effective);
                } catch (PaymentSettingsInvalidException $e) {
                    return response()->json([
                        'error' => ['code' => $e->errorCode(), 'message' => $e->getMessage(), 'fields' => $e->fields()],
                    ], 422);
                }
            }
        }

        $before = $this->settings->groupForApi($group);
        $this->settings->setGroup($group, $input, (int) $actor->getKey());
        $after = $this->settings->groupForApi($group);

        $changes = $this->diff($group, $input, $before['values'], $after['values']);
        if ($changes !== []) {
            $this->audit->record('settings.updated', ['group' => $group, 'changes' => $changes]);
            $this->versions->record('update', $group, $changes, $request);
        }

        return response()->json(['group' => $group, 'values' => $after['values'], 'secrets_set' => $after['secrets_set']]);
    }

    /** GET /admin/v1/settings/export — adminExportSettings. JSON snapshot (secrets as ciphertext). */
    public function export(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        $map = $this->settings->rawAll();
        // catalog-complete export: every key, stored value or its default (secrets stay ciphertext).
        $known = [];
        foreach (SettingsCatalog::groups() as $group => $defs) {
            foreach ($defs as $key => $def) {
                $full = "$group.$key";
                $known[$full] = array_key_exists($full, $map)
                    ? $map[$full]
                    : (($def['type'] ?? '') === 'bool' ? ($def['default'] ? '1' : '0') : (string) $def['default']);
            }
        }
        $this->audit->record('settings.exported', ['key_count' => count($known)]);

        return response()->json([
            '_meta' => [
                'app' => (string) ($this->settings->value('general', 'app_name')),
                'schema' => 2,
                'key_count' => count($known),
                'note' => 'Secrets are AES-encrypted ciphertext; they restore only on a server with the same APP_KEY.',
            ],
            'settings' => $known,
        ])->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** POST /admin/v1/settings/import — adminImportSettings. Restores known keys + versions the result. */
    public function import(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        $validated = $request->validate(['settings' => ['required', 'array']]);

        /** @var array<string, mixed> $incoming */
        $incoming = $validated['settings'];
        $this->settings->restoreRaw($incoming, (int) $request->user('admin')?->getKey());

        $applied = $this->countKnown($incoming);
        $this->audit->record('settings.imported', ['key_count' => $applied]);
        $this->versions->record('import', null, [['key' => '*', 'old' => 'previous', 'new' => "import ($applied keys)"]], $request);

        return response()->json(['ok' => true, 'applied' => $applied]);
    }

    /** GET /admin/v1/settings/versions — adminSettingsVersions. */
    public function versions(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);

        return response()->json(['data' => $this->versions->list(50)]);
    }

    /** GET /admin/v1/settings/versions/compare?from=&to= — adminCompareSettingsVersions. */
    public function compareVersions(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        $validated = $request->validate([
            'from' => ['required', 'integer'],
            'to' => ['required', 'integer'],
        ]);

        return response()->json($this->versions->compare((int) $validated['from'], (int) $validated['to']));
    }

    /** POST /admin/v1/settings/versions/{id}/restore — adminRestoreSettingsVersion. */
    public function restoreVersion(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        $version = $this->versions->restore($id, $request);
        $this->audit->record('settings.restored', ['restored_version_id' => $id, 'new_version_id' => $version->id]);

        return response()->json(['ok' => true, 'restored_from' => $id, 'new_version_id' => $version->id]);
    }

    /**
     * Effective payments settings = existing (decrypted) overlaid with incoming non-blank values. A blank
     * secret keeps the stored one (so validation uses the real secret).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function effectivePayments(array $input): array
    {
        $effective = $this->settings->group('payments');
        foreach ($input as $key => $value) {
            if ($this->settings->isSecret('payments', $key) && ($value === '' || $value === null)) {
                continue;
            }
            $effective[$key] = $value;
        }

        return $effective;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array<string, string>>
     */
    private function diff(string $group, array $input, array $before, array $after): array
    {
        $changes = [];
        foreach (array_keys($input) as $key) {
            $def = SettingsCatalog::definition($group, $key);
            if ($def === null) {
                continue;
            }
            if (($def['type'] ?? '') === 'secret') {
                if ($input[$key] !== '' && $input[$key] !== null) {
                    $changes[] = ['key' => $key, 'old' => '••••••', 'new' => '•••••• (updated)'];
                }

                continue;
            }
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[] = ['key' => $key, 'old' => $this->scalar($old), 'new' => $this->scalar($new)];
            }
        }

        return $changes;
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function countKnown(array $incoming): int
    {
        $count = 0;
        foreach (SettingsCatalog::groups() as $group => $defs) {
            foreach ($defs as $key => $_) {
                if (array_key_exists("$group.$key", $incoming)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
