<?php

namespace App\Http\Admin\V1\Controllers\Complexes;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Models\DeviceUser;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Residential complex management (the root entity). Super admin (complexes.manage) creates/edits/deletes
 * complexes and assigns complex managers; complex_manager (complexes.view) sees only its own complex.
 * Devices already link via devices.complex_id; residents surface through the device roster.
 */
class ComplexManagementController
{
    use AuthorizesAdmin;

    public function __construct(private readonly AuditLogger $audit) {}

    /** GET /admin/v1/complexes — adminListComplexesFull (complexes.view; complex_manager scoped). */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_VIEW);
        $scope = $this->complexScopeId($request);

        $complexes = Complex::query()
            ->when($scope !== null, fn ($q) => $q->whereKey($scope))
            ->orderBy('name')->get();

        return response()->json([
            'data' => $complexes->map(fn (Complex $c): array => $this->summary($c))->all(),
        ]);
    }

    /** POST /admin/v1/complexes — adminCreateComplex (complexes.manage). */
    public function store(Request $request): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        $v = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'code' => ['required', 'string', 'min:2', 'max:40', 'unique:complexes,code'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Complex $complex */
        $complex = Complex::query()->create($v + ['is_active' => true]);
        $this->audit->record('complex.created', ['code' => $complex->code], Complex::class, (int) $complex->id);

        return response()->json($this->detail($complex), 201);
    }

    /** GET /admin/v1/complexes/{complexId} — adminGetComplex (complexes.view; scoped). */
    public function show(Request $request, int $complexId): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_VIEW);
        $complex = $this->scopedComplex($request, $complexId);

        return response()->json($this->detail($complex));
    }

    /** PATCH /admin/v1/complexes/{complexId} — adminUpdateComplex (complexes.manage). */
    public function update(Request $request, int $complexId): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        /** @var Complex $complex */
        $complex = Complex::query()->findOrFail($complexId);
        $v = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:160'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $complex->forceFill($v)->save();
        $this->audit->record('complex.updated', ['code' => $complex->code], Complex::class, (int) $complex->id);

        return response()->json($this->detail($complex->refresh()));
    }

    /** DELETE /admin/v1/complexes/{complexId} — adminDeleteComplex (complexes.manage). */
    public function destroy(Request $request, int $complexId): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        /** @var Complex $complex */
        $complex = Complex::query()->findOrFail($complexId);

        if (Device::query()->where('complex_id', $complex->id)->exists()) {
            throw ValidationException::withMessages(['complex' => 'Kompleksi silmək olmaz — ona bağlı cihazlar var. Əvvəlcə cihazları köçürün.']);
        }
        AdminUser::query()->where('complex_id', $complex->id)->update(['complex_id' => null]);
        $code = $complex->code;
        $complex->delete();
        $this->audit->record('complex.deleted', ['code' => $code], Complex::class, $complexId);

        return response()->json(null, 204);
    }

    /** POST /admin/v1/complexes/{complexId}/managers — adminAssignComplexManager (complexes.manage). */
    public function assignManager(Request $request, int $complexId): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        /** @var Complex $complex */
        $complex = Complex::query()->findOrFail($complexId);
        $v = $request->validate(['admin_id' => ['required', 'integer', 'exists:admin_users,id']]);

        /** @var AdminUser $admin */
        $admin = AdminUser::query()->findOrFail($v['admin_id']);
        $admin->forceFill(['complex_id' => $complex->id])->save();
        $this->audit->record('complex.manager_assigned', ['admin_id' => $admin->id], Complex::class, (int) $complex->id);

        return response()->json($this->detail($complex));
    }

    /** DELETE /admin/v1/complexes/{complexId}/managers/{adminId} — adminUnassignComplexManager (complexes.manage). */
    public function unassignManager(Request $request, int $complexId, int $adminId): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        AdminUser::query()->where('id', $adminId)->where('complex_id', $complexId)->update(['complex_id' => null]);
        $this->audit->record('complex.manager_unassigned', ['admin_id' => $adminId], Complex::class, $complexId);

        return response()->json(null, 204);
    }

    private function scopedComplex(Request $request, int $complexId): Complex
    {
        $scope = $this->complexScopeId($request);
        if ($scope !== null && $scope !== $complexId) {
            abort(404);
        }

        return Complex::query()->findOrFail($complexId);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Complex $c): array
    {
        $deviceIds = Device::query()->where('complex_id', $c->id)->pluck('id');
        $offlineMinutes = (int) config('domain.devices.offline_threshold_minutes', 15);

        return [
            'id' => (int) $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'region_id' => $c->region_id !== null ? (int) $c->region_id : null,
            'address' => $c->address,
            'is_active' => (bool) $c->is_active,
            'stats' => [
                'devices' => $deviceIds->count(),
                'devices_online' => Device::query()->whereIn('id', $deviceIds)
                    ->where('last_online_at', '>=', now()->subMinutes($offlineMinutes))->count(),
                'residents' => DeviceUser::query()->whereIn('device_id', $deviceIds)->where('status', 'active')->distinct('user_id')->count('user_id'),
                'managers' => AdminUser::query()->where('complex_id', $c->id)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Complex $c): array
    {
        $deviceIds = Device::query()->where('complex_id', $c->id)->pluck('id');

        return $this->summary($c) + [
            'managers' => AdminUser::query()->where('complex_id', $c->id)->get()
                ->map(fn (AdminUser $a): array => ['id' => (int) $a->id, 'name' => $a->name, 'email' => $a->email, 'role' => $a->role->value])->all(),
            'devices' => Device::query()->where('complex_id', $c->id)->with('owner')->orderBy('id')->limit(200)->get()
                ->map(fn (Device $d): array => [
                    'id' => (int) $d->id,
                    'serial' => $d->serial,
                    'status' => $d->status->value,
                    'location_label' => $d->location_label,
                    'online' => $d->last_online_at !== null && $d->last_online_at->greaterThan(now()->subMinutes((int) config('domain.devices.offline_threshold_minutes', 15))),
                    'owner' => $d->owner !== null ? \App\Support\Phone\PhoneNumber::tryFromInput($d->owner->phone)?->masked() : null,
                ])->all(),
        ];
    }
}
