<?php

namespace App\Http\Admin\V1\Controllers\Complexes;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\ComplexMemberStatus;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Http\Concerns\AuthorizesAdmin;
use App\Support\Phone\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read models for the admin UI (B11): complex residents (complex_members), invitations and mobile users
 * (with account_type). residents.view; a complex_manager is locked to its own complex everywhere (other
 * complexes → 404, user list limited to its residents). Read-only — no business logic.
 */
class AdminComplexPeopleController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/complexes/{complexId}/members?status=active|removed */
    public function members(Request $request, int $complexId): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_VIEW);
        $scope = $this->complexScopeId($request);
        abort_if($scope !== null && $scope !== $complexId, 404);
        Complex::query()->findOrFail($complexId);

        $status = $request->query('status', ComplexMemberStatus::Active->value);
        $members = ComplexMember::query()->with('user')
            ->where('complex_id', $complexId)
            ->when(in_array($status, ['active', 'removed'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')->limit(500)->get();

        $deviceIds = Device::query()->where('complex_id', $complexId)->where('ownership_mode', DeviceOwnershipMode::Complex->value)->pluck('id');
        $active = Subscription::query()
            ->join('device_users as du', 'du.id', '=', 'subscriptions.device_user_id')
            ->whereIn('du.device_id', $deviceIds)->whereIn('du.user_id', $members->pluck('user_id'))
            ->where('du.status', DeviceUserStatus::Active->value)
            ->where('subscriptions.status', SubscriptionStatus::Active->value)->where('subscriptions.ends_at', '>', now())
            ->selectRaw('du.user_id, count(*) as c')->groupBy('du.user_id')->pluck('c', 'user_id');

        return response()->json(['data' => $members->map(fn (ComplexMember $m): array => [
            'user_id' => (int) $m->user_id,
            'full_name' => $m->user?->full_name,
            'email' => $m->user?->email,
            'phone' => $this->phone($request, $m->user?->phone),
            'status' => $m->status->value,
            'joined_at' => optional($m->joined_at)->toIso8601String(),
            'removed_at' => optional($m->removed_at)->toIso8601String(),
            'active_subscriptions' => (int) ($active[$m->user_id] ?? 0),
        ])->all()]);
    }

    /** GET /admin/v1/invitations?complex_id=&kind=&status= */
    public function invitations(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_VIEW);
        $scope = $this->complexScopeId($request);
        $complexId = $request->query('complex_id');
        $kind = $request->query('kind');
        $status = (string) $request->query('status', '');

        $query = Invitation::query()->whereNotNull('kind')
            // a complex_manager sees only its own complex's resident invitations
            ->when($scope !== null, fn ($q) => $q->where('kind', InvitationKind::ComplexResident->value)->where('complex_id', $scope))
            ->when($scope === null && is_numeric($complexId), fn ($q) => $q->where('complex_id', (int) $complexId))
            ->when(is_string($kind) && InvitationKind::tryFrom($kind) !== null, fn ($q) => $q->where('kind', $kind));

        if ($status === 'expired') {
            $query->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($p) => $p->where('status', 'pending')->where('expires_at', '<=', now())));
        } elseif ($status === 'pending') {
            $query->where('status', 'pending')->where('expires_at', '>', now());
        } elseif (in_array($status, ['accepted', 'declined', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->orderByDesc('id')->limit(200)->get()->map(fn (Invitation $i): array => [
            'id' => (int) $i->id,
            'kind' => $i->kind->value,
            'status' => $i->status === InvitationStatus::Pending && $i->expires_at->lessThanOrEqualTo(now()) ? 'expired' : $i->status->value,
            'complex_id' => $i->complex_id !== null ? (int) $i->complex_id : null,
            'device_id' => $i->device_id !== null ? (int) $i->device_id : null,
            'first_name' => $i->invitee_first_name,
            'last_name' => $i->invitee_last_name,
            'email' => $i->invitee_email,
            'invited_by_admin_id' => $i->invited_by_admin_id !== null ? (int) $i->invited_by_admin_id : null,
            'invited_by_user_id' => $i->invited_by_user_id !== null ? (int) $i->invited_by_user_id : null,
            'send_count' => (int) $i->send_count,
            'expires_at' => optional($i->expires_at)->toIso8601String(),
            'accepted_at' => optional($i->accepted_at)->toIso8601String(),
            'created_at' => optional($i->created_at)->toIso8601String(),
        ])->all()]);
    }

    /** GET /admin/v1/users?q=&account_type=physical|legal|none&page=&per_page= */
    public function users(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_VIEW);
        $scope = $this->complexScopeId($request);
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('account_type');

        $page = User::query()
            ->when($scope !== null, fn ($b) => $b->whereIn('id', ComplexMember::query()->select('user_id')->where('complex_id', $scope)->where('status', 'active')))
            ->when(in_array($type, ['physical', 'legal'], true), fn ($b) => $b->where('account_type', $type))
            ->when($type === 'none', fn ($b) => $b->whereNull('account_type'))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('full_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        $ids = collect($page->items())->pluck('id');
        $komendants = AdminUser::query()->whereIn('user_id', $ids)->pluck('complex_id', 'user_id');
        $complexes = ComplexMember::query()->whereIn('user_id', $ids)->where('status', 'active')->get(['user_id', 'complex_id'])->groupBy('user_id');

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u): array => [
                'id' => (int) $u->id,
                'full_name' => $u->full_name,
                'email' => $u->email,
                'phone' => $this->phone($request, $u->phone),
                'account_type' => $u->getAttributes()['account_type'] ?? null,
                'status' => $u->status->value,
                'email_verified' => $u->email_verified_at !== null,
                'is_komendant_linked' => $komendants->has($u->id),
                'complex_ids' => ($complexes->get($u->id) ?? collect())->pluck('complex_id')->map(fn ($c) => (int) $c)->values()->all(),
                'created_at' => optional($u->created_at)->toIso8601String(),
            ])->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** Full phone for global roles; masked for a complex-scoped manager. */
    private function phone(Request $request, ?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        return $this->complexScopeId($request) !== null ? (PhoneNumber::tryFromInput($phone)?->masked() ?? $phone) : $phone;
    }
}
