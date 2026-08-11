<?php

namespace App\Http\Admin\V1\Controllers\Notifications;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Queries\NotificationCampaignQuery;
use App\Domain\Notifications\Services\CampaignAudienceResolver;
use App\Domain\Notifications\Services\NotificationCampaignService;
use App\Http\Admin\V1\Requests\Notifications\NotificationCampaignCreateRequest;
use App\Http\Admin\V1\Requests\Notifications\PreviewAudienceRequest;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\NotificationCampaignResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin notification campaigns (batch 11). Send-now system announcements fanned out as push + inapp to a
 * resident audience. Authorization: notifications.view (list/preview/detail) / notifications.send (send).
 * A complex_manager is scoped to its own complex — the audience resolver intersects recipients with its
 * complex, and listing/detail is limited to the campaigns it created.
 */
class AdminNotificationCampaignController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/notifications — adminListNotificationCampaigns (notifications.view; complex_manager scoped). */
    public function index(Request $request, NotificationCampaignQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::NOTIFICATIONS_VIEW);

        $result = $query->adminList(
            status: $request->query('status'),
            createdByAdminId: $this->campaignScopeAdminId($request),
            limit: max(1, min(100, (int) $request->query('limit', 25))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => NotificationCampaignResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** POST /admin/v1/notifications/audience/preview — adminPreviewNotificationAudience (notifications.view; no side effects). */
    public function previewAudience(PreviewAudienceRequest $request, CampaignAudienceResolver $resolver): JsonResponse
    {
        $userIds = $resolver->resolve($request->validated()['audience'], $this->complexScopeId($request));

        return response()->json(['recipient_count' => count($userIds)]);
    }

    /** POST /admin/v1/notifications — adminSendNotification (notifications.send, Idempotency-Key, confirmed=true). */
    public function send(NotificationCampaignCreateRequest $request, NotificationCampaignService $service): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            throw ValidationException::withMessages(['Idempotency-Key' => __('errors.idempotency_key_required')]);
        }

        /** @var AdminUser $actor */
        $actor = $request->user();

        $campaign = $service->send(
            actor: $actor,
            data: $request->validated(),
            confirmed: $request->boolean('confirmed'),
            scopeComplexId: $this->complexScopeId($request),
            idempotencyKey: $key,
        );

        return (new NotificationCampaignResource($campaign))->response()->setStatusCode(202);
    }

    /** GET /admin/v1/notifications/{campaignId} — adminGetNotificationCampaign (notifications.view; complex_manager scoped). */
    public function show(Request $request, int $campaignId): NotificationCampaignResource
    {
        $this->requirePermission($request, Permission::NOTIFICATIONS_VIEW);

        /** @var NotificationCampaign $campaign */
        $campaign = NotificationCampaign::query()->findOrFail($campaignId);

        // A complex_manager may only see the campaigns it created — hidden as 404, not 403, so a scoped
        // admin cannot probe other admins' campaign ids.
        $scopeAdminId = $this->campaignScopeAdminId($request);
        if ($scopeAdminId !== null && (int) $campaign->created_by_admin_id !== $scopeAdminId) {
            abort(404);
        }

        return new NotificationCampaignResource($campaign);
    }

    /**
     * The admin id a complex_manager's campaign listing/detail is scoped to (itself), or null for a global
     * role (super/operator) that sees all campaigns. The campaign schema carries no complex_id, and a
     * complex_manager's every campaign is complex-scoped at send — so "its own complex's campaigns" is the
     * set of campaigns it created.
     */
    private function campaignScopeAdminId(Request $request): ?int
    {
        return $this->complexScopeId($request) !== null ? (int) $request->user()->getKey() : null;
    }
}
