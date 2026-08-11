<?php

namespace App\Http\Resources;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin AdminUser
 *
 * Maps to openapi components/schemas/AdminUser, extended for RBAC with `permissions`, `complex_id` and the
 * `impersonator_admin_id` (set when the session is an impersonation). Reads raw attributes (strict-safe) so
 * it renders both DB-loaded and freshly-built models. Secrets are never exposed (hidden on the model).
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $a */
        $a = $this->resource->getAttributes();
        $impersonator = $request->attributes->get('jwt_impersonator_id');

        return [
            'id' => (int) ($a['id'] ?? 0),
            'email' => $a['email'] ?? null,
            'name' => $a['name'] ?? null,
            'role' => (string) ($a['role'] ?? ''),
            'complex_id' => isset($a['complex_id']) && $a['complex_id'] !== null ? (int) $a['complex_id'] : null,
            'permissions' => $this->permissionKeys(),
            'phone' => $a['phone'] ?? null,
            'is_2fa_enabled' => (bool) ($a['is_2fa_enabled'] ?? false),
            'status' => (string) ($a['status'] ?? 'active'),
            'impersonator_admin_id' => is_numeric($impersonator) ? (int) $impersonator : null,
            'last_login_at' => $this->iso($a['last_login_at'] ?? null),
            'created_at' => $this->iso($a['created_at'] ?? null),
        ];
    }

    private function iso(mixed $value): ?string
    {
        return $value !== null && $value !== '' ? Carbon::parse($value)->toIso8601String() : null;
    }
}
