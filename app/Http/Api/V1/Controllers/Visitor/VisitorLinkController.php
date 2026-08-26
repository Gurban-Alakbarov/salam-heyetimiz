<?php

namespace App\Http\Api\V1\Controllers\Visitor;

use App\Domain\DeviceComm\Exceptions\OpenNotPermittedException;
use App\Domain\Devices\Models\Device;
use App\Domain\Subscriptions\Enums\SuspensionReason;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Domain\Visitor\Models\VisitorLink;
use App\Domain\Visitor\Queries\VisitorLinkQuery;
use App\Domain\Visitor\Services\VisitorLinkService;
use App\Http\Api\V1\Requests\Visitor\CreateVisitorLinkRequest;
use App\Http\Resources\VisitorLinkResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resident visitor links (auth:user). A resident may share access they themselves hold — so creation is
 * gated on the SAME open-permission read as opening (canOpen), and residents only ever see/revoke their
 * own links. All business logic is VisitorLinkService (shared with the admin controller).
 */
class VisitorLinkController
{
    public function __construct(private readonly SubscriptionStatusQuery $access) {}

    /** POST /v1/devices/{deviceId}/visitor-links — create (201; token returned once). */
    public function store(CreateVisitorLinkRequest $request, int $deviceId, VisitorLinkService $service): JsonResponse
    {
        $device = Device::query()->findOrFail($deviceId);
        abort_unless($request->user()?->can('view', $device) ?? false, 404);
        $this->assertCanShare((int) $request->user()->getKey(), $deviceId);

        $result = $service->create($device, $request->user(), $request->toData());

        return response()->json([
            'link' => (new VisitorLinkResource($result['link']))->toArray($request),
            'token' => $result['token'],
            'url' => $result['url'],
        ], 201);
    }

    /** GET /v1/devices/{deviceId}/visitor-links — the caller's own links for this device. */
    public function index(Request $request, int $deviceId): JsonResponse
    {
        $device = Device::query()->findOrFail($deviceId);
        abort_unless($request->user()?->can('view', $device) ?? false, 404);

        $links = VisitorLink::query()
            ->where('device_id', $deviceId)
            ->where('created_by_user_id', (int) $request->user()->getKey())
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => VisitorLinkResource::collection($links)]);
    }

    /**
     * GET /v1/visitor-links — the caller's OWN visitor links across ALL their devices ("Dəvətlərim").
     * Ownership is enforced in the query (created_by_user_id); a user can never see another's links.
     * Optional ?status=active|used|expired|revoked (server-side filter) + ?cursor (id pagination).
     */
    public function mine(Request $request, VisitorLinkQuery $query): JsonResponse
    {
        $result = $query->forUser(
            userId: (int) $request->user()->getKey(),
            status: $request->query('status'),
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => VisitorLinkResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** POST /v1/visitor-links/{id}/revoke — revoke the caller's own link. */
    public function revoke(Request $request, int $id, VisitorLinkService $service): JsonResponse
    {
        $link = VisitorLink::query()
            ->where('id', $id)
            ->where('created_by_user_id', (int) $request->user()->getKey())
            ->firstOrFail();

        $service->revoke($link, $request->user());

        return response()->json(['data' => new VisitorLinkResource($link->refresh())]);
    }

    /** You can only share access you hold — mirror the open-command permission check exactly. */
    private function assertCanShare(int $userId, int $deviceId): void
    {
        $result = $this->access->for($userId, $deviceId);
        if ($result->canOpen) {
            return;
        }

        throw match ($result->suspensionReason) {
            SuspensionReason::DeviceDisabled => OpenNotPermittedException::deviceDisabled($deviceId),
            SuspensionReason::SubscriptionExpired,
            SuspensionReason::OwnerSubExpiredOthersActive => OpenNotPermittedException::subscriptionRequired($deviceId),
            default => OpenNotPermittedException::forbidden(),
        };
    }
}
