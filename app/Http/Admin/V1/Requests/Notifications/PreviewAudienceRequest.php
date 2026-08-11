<?php

namespace App\Http\Admin\V1\Requests\Notifications;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates adminPreviewNotificationAudience ({ audience: AudienceSpec }). Gated on notifications.view.
 * Preview only resolves a recipient count — it creates no campaign and sends nothing.
 */
class PreviewAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::NOTIFICATIONS_VIEW);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
