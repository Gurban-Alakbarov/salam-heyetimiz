<?php

namespace App\Http\Admin\V1\Controllers\Search;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\Complex;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Users\Models\User;
use App\Http\Concerns\AuthorizesAdmin;
use App\Support\Phone\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /admin/v1/search?q= — adminGlobalSearch (panel header). One box for the things operators look up all day:
 * devices (IMEI / name / SIM), people (name / email / phone), complexes (name / code) and orders (number / bank id).
 * Each group appears only with its own view permission and stays inside a komendant's complex; at most 5 per group.
 */
final class AdminSearchController
{
    use AuthorizesAdmin;

    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin !== null, 401);
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['devices' => [], 'people' => [], 'complexes' => [], 'orders' => []]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $scope = $this->complexScopeId($request);

        $devices = [];
        if ($admin->hasPermission(Permission::DEVICES_VIEW)) {
            $devices = Device::query()
                ->when($scope !== null, fn ($b) => $b->where('complex_id', $scope))
                ->where(fn ($w) => $w->where('serial', 'like', $like)->orWhere('location_label', 'like', $like)->orWhere('sim_phone', 'like', $like))
                ->orderByDesc('id')->limit(self::PER_GROUP)
                ->get(['id', 'serial', 'location_label', 'status', 'last_online_at'])
                ->map(fn (Device $d): array => [
                    'id' => (int) $d->id,
                    'label' => $d->location_label ?: $d->serial,
                    'serial' => $d->serial,
                    'status' => $d->status?->value,
                ])->all();
        }

        $people = [];
        if ($admin->hasPermission(Permission::RESIDENTS_VIEW)) {
            $people = User::query()
                ->when($scope !== null, fn ($b) => $b->whereIn('id', ComplexMember::query()->select('user_id')->where('complex_id', $scope)->where('status', 'active')))
                ->where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                ->orderByDesc('id')->limit(self::PER_GROUP)
                ->get(['id', 'full_name', 'email', 'phone'])
                ->map(fn (User $u): array => [
                    'id' => (int) $u->id,
                    'name' => $u->full_name,
                    'email' => $u->email,
                    'phone' => $u->phone === null ? null
                        : ($scope !== null ? (PhoneNumber::tryFromInput($u->phone)?->masked() ?? $u->phone) : $u->phone),
                ])->all();
        }

        $complexes = [];
        if ($admin->hasPermission(Permission::COMPLEXES_VIEW)) {
            $complexes = Complex::query()
                ->when($scope !== null, fn ($b) => $b->whereKey($scope))
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orderBy('name')->limit(self::PER_GROUP)
                ->get(['id', 'name', 'code'])
                ->map(fn (Complex $c): array => ['id' => (int) $c->id, 'name' => $c->name, 'code' => $c->code])->all();
        }

        $orders = [];
        if ($admin->hasPermission(Permission::ORDERS_VIEW)) { // never held by a komendant (not complex-scoped)
            $orders = Order::query()
                ->where(fn ($w) => $w->where('bank_order_id', 'like', $like)
                    ->when(ctype_digit($q), fn ($x) => $x->orWhere('id', (int) $q)))
                ->orderByDesc('id')->limit(self::PER_GROUP)
                ->get(['id', 'amount_minor', 'currency', 'status'])
                ->map(fn (Order $o): array => [
                    'id' => (int) $o->id,
                    'amount_minor' => (int) $o->amount_minor,
                    'currency' => $o->currency,
                    'status' => $o->status?->value,
                ])->all();
        }

        return response()->json(['devices' => $devices, 'people' => $people, 'complexes' => $complexes, 'orders' => $orders]);
    }
}
