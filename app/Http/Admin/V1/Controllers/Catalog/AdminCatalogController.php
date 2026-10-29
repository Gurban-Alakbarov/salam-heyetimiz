<?php

namespace App\Http\Admin\V1\Controllers\Catalog;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Catalog\Models\DeviceModel;
use App\Domain\Catalog\Models\Region;
use App\Domain\Catalog\Models\SimOperator;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Device catalog in the panel (admin audit phase 3) — until now device models, regions and SIM operators lived only
 * in seeders / the DB. catalog.manage (super admin by default). Rows are never deleted (devices reference them):
 * they are switched off with is_active, which hides them from the device form. A device model's open / close
 * commands and pulse drive the live barriers, so every change is audited with the old → new values.
 */
final class AdminCatalogController
{
    use AuthorizesAdmin;

    private const DRIVERS = ['traccar', 'ble', 'sms'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** GET /admin/v1/catalog — every row, inactive ones included. */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::CATALOG_MANAGE);

        return response()->json([
            'device_models' => DeviceModel::query()->orderBy('vendor')->orderBy('model_code')->get()->map(fn (DeviceModel $m): array => $this->model($m))->all(),
            'regions' => Region::query()->orderBy('name')->get()->map(fn (Region $r): array => [
                'id' => (int) $r->id, 'code' => $r->code, 'name' => $r->name,
                'parent_id' => $r->parent_id !== null ? (int) $r->parent_id : null, 'is_active' => (bool) $r->is_active,
            ])->all(),
            'sim_operators' => SimOperator::query()->orderBy('name')->get()->map(fn (SimOperator $o): array => [
                'id' => (int) $o->id, 'code' => $o->code, 'name' => $o->name,
                'country_iso' => $o->country_iso, 'mcc_mnc' => $o->mcc_mnc, 'is_active' => (bool) $o->is_active,
            ])->all(),
        ]);
    }

    /** POST /admin/v1/catalog/device-models */
    public function storeModel(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::CATALOG_MANAGE);
        $v = $request->validate($this->modelRules(null));
        $m = DeviceModel::query()->create($v);
        $this->audit->record('catalog.device_model_created', ['values' => $v], DeviceModel::class, (int) $m->id);

        return response()->json($this->model($m->refresh()), 201);
    }

    /** PATCH /admin/v1/catalog/device-models/{id} */
    public function updateModel(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::CATALOG_MANAGE);
        $m = DeviceModel::query()->findOrFail($id);
        $v = $request->validate($this->modelRules($m));
        $this->saveAudited($m, $v, 'catalog.device_model_updated');

        return response()->json($this->model($m->refresh()));
    }

    /** POST /admin/v1/catalog/regions */
    public function storeRegion(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::CATALOG_MANAGE);
        $v = $request->validate($this->regionRules(null));
        $r = Region::query()->create($v);
        $this->audit->record('catalog.region_created', ['values' => $v], Region::class, (int) $r->id);

        return response()->json(['id' => (int) $r->id], 201);
    }

    /** PATCH /admin/v1/catalog/regions/{id} */
    public function updateRegion(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::CATALOG_MANAGE);
        $r = Region::query()->findOrFail($id);
        $this->saveAudited($r, $request->validate($this->regionRules($r)), 'catalog.region_updated');

        return response()->json(['id' => (int) $r->id]);
    }

    /** POST /admin/v1/catalog/sim-operators */
    public function storeOperator(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::CATALOG_MANAGE);
        $v = $request->validate($this->operatorRules(null));
        $o = SimOperator::query()->create($v);
        $this->audit->record('catalog.sim_operator_created', ['values' => $v], SimOperator::class, (int) $o->id);

        return response()->json(['id' => (int) $o->id], 201);
    }

    /** PATCH /admin/v1/catalog/sim-operators/{id} */
    public function updateOperator(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::CATALOG_MANAGE);
        $o = SimOperator::query()->findOrFail($id);
        $this->saveAudited($o, $request->validate($this->operatorRules($o)), 'catalog.sim_operator_updated');

        return response()->json(['id' => (int) $o->id]);
    }

    /** @param  array<string, mixed>  $values */
    private function saveAudited(Model $row, array $values, string $action): void
    {
        $changes = [];
        foreach ($values as $key => $new) {
            $old = $row->getAttribute($key);
            $oldCmp = $old instanceof \BackedEnum ? $old->value : $old;
            if ((string) json_encode($oldCmp) !== (string) json_encode($new)) {
                $changes[] = ['key' => $key, 'old' => $oldCmp, 'new' => $new];
            }
        }
        $row->forceFill($values)->save();
        $this->audit->record($action, ['changes' => $changes], $row::class, (int) $row->getKey());
    }

    /** @return array<string, mixed> */
    private function modelRules(?DeviceModel $m): array
    {
        $req = $m === null ? 'required' : 'sometimes';
        $vendor = (string) request()->input('vendor', $m?->vendor ?? '');

        return [
            'vendor' => [$req, 'string', 'min:1', 'max:60'],
            'model_code' => [$req, 'string', 'min:1', 'max:60',
                Rule::unique('device_models', 'model_code')->where('vendor', $vendor)->ignore($m?->id)],
            'default_driver_type' => ['sometimes', Rule::in(self::DRIVERS)],
            'fallback_open_driver' => ['sometimes', 'nullable', Rule::in(self::DRIVERS)],
            'whitelist_capacity' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'sms_open_command' => ['sometimes', 'nullable', 'string', 'max:40'],
            'open_command' => ['sometimes', 'nullable', 'string', 'max:40'],
            'close_command' => ['sometimes', 'nullable', 'string', 'max:40'],
            'command_result_confirm' => ['sometimes', 'boolean'],
            'open_pulse_ms' => ['sometimes', 'nullable', 'integer', 'min:100', 'max:60000'],
            'supports_clip' => ['sometimes', 'boolean'],
            'supports_sms' => ['sometimes', 'boolean'],
            'supports_mqtt' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function regionRules(?Region $r): array
    {
        $req = $r === null ? 'required' : 'sometimes';

        return [
            'code' => [$req, 'string', 'min:1', 'max:20', Rule::unique('regions', 'code')->ignore($r?->id)],
            'name' => [$req, 'string', 'min:1', 'max:80'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function operatorRules(?SimOperator $o): array
    {
        $req = $o === null ? 'required' : 'sometimes';

        return [
            'code' => [$req, 'string', 'min:1', 'max:20', Rule::unique('sim_operators', 'code')->ignore($o?->id)],
            'name' => [$req, 'string', 'min:1', 'max:60'],
            'country_iso' => ['sometimes', 'string', 'size:2'],
            'mcc_mnc' => ['sometimes', 'nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function model(DeviceModel $m): array
    {
        $enum = fn ($v) => $v instanceof \BackedEnum ? $v->value : $v;

        return [
            'id' => (int) $m->id,
            'vendor' => $m->vendor,
            'model_code' => $m->model_code,
            'default_driver_type' => $enum($m->default_driver_type),
            'fallback_open_driver' => $enum($m->fallback_open_driver),
            'whitelist_capacity' => (int) $m->whitelist_capacity,
            'sms_open_command' => $m->sms_open_command,
            'open_command' => $m->open_command,
            'close_command' => $m->close_command,
            'command_result_confirm' => (bool) $m->command_result_confirm,
            'open_pulse_ms' => $m->open_pulse_ms !== null ? (int) $m->open_pulse_ms : null,
            'supports_clip' => (bool) $m->supports_clip,
            'supports_sms' => (bool) $m->supports_sms,
            'supports_mqtt' => (bool) $m->supports_mqtt,
            'notes' => $m->notes,
            'is_active' => (bool) $m->is_active,
            'devices_count' => \App\Domain\Devices\Models\Device::query()->where('device_model_id', $m->id)->count(),
        ];
    }
}
