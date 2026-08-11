<?php

namespace App\Http\Api\V1\Controllers\Devices;

use App\Domain\Devices\Enums\DeviceListFilter;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Queries\DeviceQuery;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Http\Resources\DeviceDetailResource;
use App\Http\Resources\DeviceResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController
{
    public function __construct(
        private readonly SubscriptionStatusQuery $access,
        private readonly SubscriptionQuery $subscriptions,
    ) {}

    /** GET /v1/devices — listMyDevices (caller's owned + member devices). */
    public function index(Request $request, DeviceQuery $query): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        $result = $query->forUser(
            userId: $userId,
            filter: DeviceListFilter::tryFrom((string) $request->query('filter', 'all')) ?? DeviceListFilter::All,
            limit: $this->limit($request),
            cursor: Cursor::decode($request->query('cursor')),
        );

        $result['data']->each(fn (Device $device) => $this->attachCallerContext($device, $userId));

        return response()->json([
            'data' => DeviceResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** GET /v1/devices/{deviceId} — getDevice (DeviceDetail). */
    public function show(Request $request, int $deviceId): DeviceDetailResource
    {
        $device = Device::query()->with('model')->findOrFail($deviceId);

        // Non-members get 404 (no existence leak).
        abort_unless($request->user()?->can('view', $device) ?? false, 404);

        $userId = (int) $request->user()->getKey();
        $this->attachCallerContext($device, $userId);
        $device->subscription_brief = $this->subscriptions->forUserDevice($userId, $deviceId);

        return new DeviceDetailResource($device);
    }

    private function attachCallerContext(Device $device, int $userId): void
    {
        $result = $this->access->for($userId, (int) $device->getKey());

        $device->caller_role = (int) $device->owner_user_id === $userId
            ? DeviceUserRole::Owner->value
            : DeviceUserRole::User->value;
        $device->caller_can_open = $result->canOpen;
        $device->caller_suspension_reason = $result->suspensionReason->value;
    }

    private function limit(Request $request): int
    {
        return max(1, min(100, (int) $request->query('limit', 25)));
    }
}
