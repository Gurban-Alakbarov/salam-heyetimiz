<?php

namespace App\Http\Api\V1\Controllers\Notifications;

use App\Domain\Notifications\Queries\NotificationQuery;
use App\Domain\Notifications\Services\NotificationInboxService;
use App\Http\Resources\NotificationResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notification inbox (openapi listNotifications / markNotificationRead / markAllNotificationsRead).
 * All routes are auth:user and strictly scoped to the caller — a user never reads another user's inbox.
 * The inbox surfaces the durable `inapp` rows; push/sms/email rows are delivery records (NotificationQuery).
 */
class NotificationController
{
    /** GET /v1/notifications — listNotifications (cursor-paginated, newest first, + unread_count). */
    public function index(Request $request, NotificationQuery $query): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        $result = $query->inboxForUser(
            userId: $userId,
            status: $request->query('status'),
            limit: $this->limit($request),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => NotificationResource::collection($result['data']),
            'page' => $result['page'],
            'unread_count' => $query->unreadCount($userId),
        ]);
    }

    /** POST /v1/notifications/{notificationId}/read — markNotificationRead (204; 404 if not owned). */
    public function read(Request $request, int $notificationId, NotificationInboxService $service): JsonResponse
    {
        abort_unless($service->markRead((int) $request->user()->getKey(), $notificationId), 404);

        return response()->json(null, 204);
    }

    /** POST /v1/notifications/read-all — markAllNotificationsRead (204; idempotent). */
    public function readAll(Request $request, NotificationInboxService $service): JsonResponse
    {
        $service->markAllRead((int) $request->user()->getKey());

        return response()->json(null, 204);
    }

    private function limit(Request $request): int
    {
        return max(1, min(100, (int) $request->query('limit', 25)));
    }
}
