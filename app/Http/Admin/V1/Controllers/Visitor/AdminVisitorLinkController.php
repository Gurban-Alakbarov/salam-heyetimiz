<?php

namespace App\Http\Admin\V1\Controllers\Visitor;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Devices\Models\Device;
use App\Domain\Visitor\Models\VisitorLink;
use App\Domain\Visitor\Models\VisitorLinkUsage;
use App\Domain\Visitor\Queries\VisitorLinkListFilters;
use App\Domain\Visitor\Queries\VisitorLinkQuery;
use App\Domain\Visitor\Services\VisitorLinkService;
use App\Http\Api\V1\Requests\Visitor\CreateVisitorLinkRequest;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\VisitorLinkAdminResource;
use App\Http\Resources\VisitorLinkUsageResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin visitor links — the SAME feature and the SAME VisitorLinkService as the resident flow, gated by
 * permission and complex-scoped for complex_manager. Admins can create, list/filter every link
 * (active/expired/revoked, usage, creator), inspect a link's usage log, and revoke immediately.
 */
class AdminVisitorLinkController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/visitor-links — directory with server-side filters (visitor_links.view; complex-scoped). */
    public function index(Request $request, VisitorLinkQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::VISITOR_LINKS_VIEW);

        $filters = new VisitorLinkListFilters(
            deviceId: $request->query('device_id') !== null ? (int) $request->query('device_id') : null,
            status: $request->query('status'),
            q: $request->query('q'),
            purpose: $request->query('purpose'),
            accessType: $request->query('access_type'),
            createdBy: $request->query('created_by'),
            createdFrom: $request->query('created_from'),
            createdTo: $request->query('created_to'),
        );

        $result = $query->adminList(
            filters: $filters,
            scopeComplexId: $this->complexScopeId($request),
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => VisitorLinkAdminResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** GET /admin/v1/visitor-links/{id}/usages — a link's audited open attempts (visitor_links.view). */
    public function usages(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::VISITOR_LINKS_VIEW);

        $link = VisitorLink::query()->with('device')->findOrFail($id);
        $this->assertDeviceInScope($request, $link->device);

        $limit = max(1, min(100, (int) $request->query('limit', 25)));
        $cursor = Cursor::decode($request->query('cursor'));

        $rows = VisitorLinkUsage::query()
            ->where('visitor_link_id', $link->getKey())
            ->when($cursor !== null, fn ($q) => $q->where('id', '<', $cursor))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return response()->json([
            'data' => VisitorLinkUsageResource::collection($data),
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->getKey()) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ]);
    }

    /** POST /admin/v1/devices/{deviceId}/visitor-links — create (visitor_links.manage). */
    public function store(CreateVisitorLinkRequest $request, int $deviceId, VisitorLinkService $service): JsonResponse
    {
        $this->requirePermission($request, Permission::VISITOR_LINKS_MANAGE);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertDeviceInScope($request, $device);

        $result = $service->create($device, $request->user(), $request->toData());

        return response()->json([
            'link' => (new VisitorLinkAdminResource($result['link']->load(['device', 'createdByAdmin'])))->toArray($request),
            'token' => $result['token'],
            'url' => $result['url'],
        ], 201);
    }

    /** POST /admin/v1/visitor-links/{id}/revoke — revoke immediately (visitor_links.manage). */
    public function revoke(Request $request, int $id, VisitorLinkService $service): JsonResponse
    {
        $this->requirePermission($request, Permission::VISITOR_LINKS_MANAGE);

        $link = VisitorLink::query()->with('device')->findOrFail($id);
        $this->assertDeviceInScope($request, $link->device);

        $service->revoke($link, $request->user());

        return response()->json(['data' => new VisitorLinkAdminResource($link->refresh()->load(['device', 'createdByUser', 'createdByAdmin']))]);
    }
}
