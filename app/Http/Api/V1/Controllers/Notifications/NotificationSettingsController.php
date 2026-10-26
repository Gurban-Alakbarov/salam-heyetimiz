<?php

namespace App\Http\Api\V1\Controllers\Notifications;

use App\Domain\Notifications\Services\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O13 — the caller's notification switches (auth:user). Lists every notification the user may switch off with
 * its push state, and switches one. Off = no push; the in-app inbox still records it.
 */
class NotificationSettingsController
{
    /** GET /v1/me/notification-settings — getNotificationSettings */
    public function index(Request $request, NotificationPreferences $preferences): JsonResponse
    {
        return response()->json(['data' => $preferences->forUser((int) $request->user()->getKey())]);
    }

    /** PUT /v1/me/notification-settings — updateNotificationSetting {template_key, push_enabled}; 422 notification_not_mutable */
    public function update(Request $request, NotificationPreferences $preferences): JsonResponse
    {
        $data = $request->validate([
            'template_key' => ['required', 'string', 'max:80'],
            'push_enabled' => ['required', 'boolean'],
        ]);
        $userId = (int) $request->user()->getKey();

        $preferences->setPush($userId, $data['template_key'], (bool) $data['push_enabled']);

        return response()->json(['data' => $preferences->forUser($userId)]);
    }
}
