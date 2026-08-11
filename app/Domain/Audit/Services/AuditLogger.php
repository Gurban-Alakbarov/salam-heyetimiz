<?php

namespace App\Domain\Audit\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Users\Models\User;
use App\Support\Audit\AuditableEvent;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Writes administrative audit entries. The actor + impersonator + request context are resolved from the
 * current request automatically, so callers (the generic AuditableEvent recorder and explicit
 * admin-action calls: impersonation, admin CRUD, settings) only supply the action + payload.
 */
final class AuditLogger
{
    public function __construct(private readonly Clock $clock) {}

    public function fromEvent(AuditableEvent $event): void
    {
        $this->record($event->auditAction(), $event->auditPayload());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $action, array $payload = [], ?string $auditableType = null, ?int $auditableId = null): void
    {
        [$kind, $id, $label] = $this->actor();
        $request = RequestFacade::instance();

        AuditLog::query()->create([
            'actor_kind' => $kind,
            'actor_id' => $id,
            'actor_label' => $label,
            'impersonator_admin_id' => $this->impersonatorId(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'payload' => $payload === [] ? null : $payload,
            'ip' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => $this->clock->now(),
        ]);
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?string}
     */
    private function actor(): array
    {
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof AdminUser) {
            return ['admin', (int) $admin->getKey(), $admin->email];
        }

        $user = Auth::guard('user')->user();
        if ($user instanceof User) {
            return ['user', (int) $user->getKey(), $user->phone];
        }

        return ['system', null, null];
    }

    private function impersonatorId(): ?int
    {
        $value = RequestFacade::instance()?->attributes->get('jwt_impersonator_id');

        return is_numeric($value) ? (int) $value : null;
    }
}
