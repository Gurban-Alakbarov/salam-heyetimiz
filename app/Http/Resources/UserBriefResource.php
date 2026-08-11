<?php

namespace App\Http\Resources;

use App\Domain\Users\Models\User;
use App\Support\Phone\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * Maps to openapi components/schemas/UserBrief. Phone is masked (R-SEC-15) — the full number is
 * never exposed in roster/owner contexts. `email` IS returned in full: this resource is rendered
 * only in ADMIN responses (DeviceAdminResource owner, DeviceAdminDetailResource roster,
 * AdminDeviceRosterController) — never on the mobile surface — and admins need a way to identify
 * the account behind a roster row. Null for phone-only accounts.
 */
class UserBriefResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'phone_masked' => PhoneNumber::tryFromInput($this->phone)?->masked() ?? $this->phone,
            'email' => $this->email,
            'full_name' => $this->full_name,
            // True when the account was deleted from the system (soft delete) but its revoked roster
            // row is still shown for history — the panel renders a "silinib" marker for these.
            'deleted' => $this->deleted_at !== null,
        ];
    }
}
