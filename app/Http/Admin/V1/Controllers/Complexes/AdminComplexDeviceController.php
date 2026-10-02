<?php

namespace App\Http\Admin\V1\Controllers\Complexes;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\Complex;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Concerns\PresentsAdminDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Complex ↔ device binding and the device access model (IMPLEMENTATION_PLAN §4.4 / §15 / B11). Every change
 * runs in one transaction on a row-locked device and keeps the B4 invariants:
 *  - bind / unbind: complexes.manage; a complex-mode device can never be left without its complex;
 *  - private → complex: devices.update; only when bound to a complex, with no owner and no active roster;
 *  - complex → private: devices.update; only with no active roster and no active subscription.
 * Refusals are 409 with a specific code. complex_manager scope: other complexes' devices answer 404.
 */
class AdminComplexDeviceController
{
    use AuthorizesAdmin;
    use PresentsAdminDevice;

    public function __construct(private readonly AuditLogger $audit) {}

    /** POST /admin/v1/complexes/{complexId}/devices/{deviceId} */
    public function bind(Request $request, int $complexId, int $deviceId): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        $complex = $this->scopedComplex($request, $complexId);

        return $this->locked($deviceId, function (Device $device) use ($complex): JsonResponse {
            if ($device->complex_id !== null && (int) $device->complex_id !== (int) $complex->id) {
                return $this->conflict('device_in_other_complex', 'Cihaz başqa kompleksə bağlıdır; əvvəlcə ayırın.');
            }
            $device->forceFill(['complex_id' => $complex->id])->save();
            $this->audit->record('complex.device_bound', ['complex_id' => (int) $complex->id, 'device_id' => (int) $device->id], Device::class, (int) $device->id);

            return $this->adminDevice($device->refresh())->response();
        });
    }

    /** DELETE /admin/v1/complexes/{complexId}/devices/{deviceId} */
    public function unbind(Request $request, int $complexId, int $deviceId): JsonResponse
    {
        $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        $complex = $this->scopedComplex($request, $complexId);

        return $this->locked($deviceId, function (Device $device) use ($complex): JsonResponse {
            if ((int) $device->complex_id !== (int) $complex->id) {
                abort(404);
            }
            if ($device->ownership_mode === DeviceOwnershipMode::Complex) {
                return $this->conflict('device_is_complex_mode', 'Kompleks rejimli cihaz kompleksdən ayrıla bilməz; əvvəlcə şəxsi rejimə keçirin.');
            }
            $device->forceFill(['complex_id' => null])->save();
            $this->audit->record('complex.device_unbound', ['complex_id' => (int) $complex->id, 'device_id' => (int) $device->id], Device::class, (int) $device->id);

            return $this->adminDevice($device->refresh())->response();
        });
    }

    /** POST /admin/v1/devices/{deviceId}/ownership-mode {ownership_mode: private|complex} */
    public function changeMode(Request $request, int $deviceId): JsonResponse
    {
        $this->requirePermission($request, Permission::DEVICES_UPDATE);
        $to = DeviceOwnershipMode::from($request->validate([
            'ownership_mode' => ['required', Rule::enum(DeviceOwnershipMode::class)],
        ])['ownership_mode']);
        $scoped = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $scoped);

        return $this->locked($deviceId, function (Device $device) use ($to): JsonResponse {
            $from = $device->ownership_mode;
            if ($from === $to) {
                return $this->adminDevice($device)->response();
            }

            $hasActiveRoster = DeviceUser::query()->where('device_id', $device->id)->where('status', DeviceUserStatus::Active->value)->exists();
            if ($to === DeviceOwnershipMode::Complex) {
                if ($device->complex_id === null) {
                    return $this->conflict('device_not_in_complex', 'Kompleks rejimi üçün cihaz əvvəlcə kompleksə bağlanmalıdır.');
                }
                if ($device->owner_user_id !== null || $hasActiveRoster) {
                    return $this->conflict('device_has_owner_or_roster', 'Cihazın sahibi və ya aktiv istifadəçiləri var; kompleks rejiminə keçmək olmaz.');
                }
            } else {
                $hasActiveSubscription = Subscription::query()
                    ->whereIn('device_user_id', DeviceUser::query()->select('id')->where('device_id', $device->id))
                    ->where('status', SubscriptionStatus::Active->value)
                    ->where('ends_at', '>', now())
                    ->exists();
                if ($hasActiveRoster || $hasActiveSubscription) {
                    return $this->conflict('device_has_roster_or_subscription', 'Cihazda aktiv sakin və ya abunəlik var; şəxsi rejimə keçmək olmaz.');
                }
            }

            $device->forceFill(['ownership_mode' => $to->value])->save();
            $this->audit->record('device.ownership_mode_changed', [
                'device_id' => (int) $device->id, 'from' => $from->value, 'to' => $to->value, 'complex_id' => $device->complex_id,
            ], Device::class, (int) $device->id);

            return $this->adminDevice($device->refresh())->response();
        });
    }

    /** @param  callable(Device): JsonResponse  $fn */
    private function locked(int $deviceId, callable $fn): JsonResponse
    {
        return DB::transaction(function () use ($deviceId, $fn): JsonResponse {
            /** @var Device $device */
            $device = Device::query()->whereKey($deviceId)->lockForUpdate()->firstOrFail();

            return $fn($device);
        });
    }

    private function scopedComplex(Request $request, int $complexId): Complex
    {
        $scope = $this->complexScopeId($request);
        abort_if($scope !== null && $scope !== $complexId, 404);

        return Complex::query()->findOrFail($complexId);
    }

    private function conflict(string $code, string $message): JsonResponse
    {
        $requestId = Context::get('request_id');

        return response()->json(['error' => [
            'code' => $code, 'message_key' => 'errors.'.$code, 'message' => $message, 'details' => null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]], 409);
    }
}
