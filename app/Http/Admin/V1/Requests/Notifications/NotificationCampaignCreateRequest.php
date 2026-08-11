<?php

namespace App\Http\Admin\V1\Requests\Notifications;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates adminSendNotification (NotificationCampaignCreate). Gated on notifications.send (403 precedes
 * body validation). `confirmed` is validated as a boolean here but its truthiness is enforced downstream —
 * a non-true value is a 409 (confirmation_required), not a 422 — so it is intentionally not `accepted`.
 */
class NotificationCampaignCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::NOTIFICATIONS_SEND);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:system'],
            'title' => ['required', 'string', 'min:1', 'max:160'],
            'body' => ['required', 'string', 'min:1'],
            'language' => ['required', 'string', 'in:az,ru,en'],
            'confirmed' => ['nullable', 'boolean'],
            'audience' => ['required', 'array'],
            'audience.scope' => ['required', 'string', 'in:all_users,user_ids,filter,complex'],
            'audience.user_ids' => ['nullable', 'array'],
            'audience.user_ids.*' => ['integer'],
            'audience.filter' => ['nullable', 'array'],
            'audience.filter.q' => ['nullable', 'string'],
            'audience.filter.complex_id' => ['nullable', 'integer'],
            'audience.filter.role' => ['nullable', 'string'],
            'audience.filter.subscription_status' => ['nullable', 'string'],
            'audience.complex_id' => ['nullable', 'integer'],
        ];
    }
}
