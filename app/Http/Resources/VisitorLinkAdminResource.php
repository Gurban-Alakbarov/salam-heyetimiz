<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Admin view of a visitor link: the base link fields plus device + creator context, so the admin panel
 * can show/filter every link (active/expired/revoked), its usage, and who created it.
 */
class VisitorLinkAdminResource extends VisitorLinkResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'token_prefix' => $this->token_prefix,
            'device' => $this->relationLoaded('device') && $this->device !== null
                ? ['id' => (int) $this->device->id, 'serial' => $this->device->serial, 'label' => $this->device->location_label ?? $this->device->serial]
                : null,
            'created_by' => $this->createdBy(),
        ]);
    }

    /** @return array<string, mixed> */
    private function createdBy(): array
    {
        if ($this->created_by_admin_id !== null) {
            $admin = $this->relationLoaded('createdByAdmin') ? $this->createdByAdmin : null;

            return ['type' => 'admin', 'id' => (int) $this->created_by_admin_id, 'label' => $admin?->email];
        }

        $user = $this->relationLoaded('createdByUser') ? $this->createdByUser : null;

        return ['type' => 'resident', 'id' => $this->created_by_user_id !== null ? (int) $this->created_by_user_id : null, 'label' => $user?->email ?? $user?->phone];
    }
}
