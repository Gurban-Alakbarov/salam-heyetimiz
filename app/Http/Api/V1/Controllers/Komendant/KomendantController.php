<?php

namespace App\Http\Api\V1\Controllers\Komendant;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Actions\RemoveComplexResident;
use App\Domain\Roster\Enums\ComplexMemberStatus;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Support\SubscriptionPriceResolver;
use App\Domain\Users\Models\User;
use App\Http\Middleware\EnsureKomendant;
use App\Support\Phone\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

/**
 * Komendant (complex manager) mobile surface — IMPLEMENTATION_PLAN §12 / B5. Every endpoint is behind
 * auth:user + `komendant` (EnsureKomendant) and is scoped to the linked manager's complex; each action also
 * requires the manager's existing RBAC permission (residents.view/create/delete, devices.view,
 * complexes.view). Out-of-scope ids answer 404. Deliberately absent (BR-11): granting a free subscription,
 * changing prices, binding devices — a Komendant only invites, views and removes residents.
 */
class KomendantController
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly ComplexMembershipService $memberships,
    ) {}

    /** GET /v1/komendant/complex */
    public function complex(Request $request): JsonResponse
    {
        $manager = $this->manager($request, Permission::COMPLEXES_VIEW);
        $complex = $manager->complex;
        $complexId = (int) $complex->getKey();

        return response()->json(['data' => [
            'id' => $complexId,
            'name' => $complex->name,
            'address' => $complex->address,
            'stats' => [
                'devices' => $this->complexDevices($complexId)->count(),
                'residents' => ComplexMember::query()->where('complex_id', $complexId)->where('status', ComplexMemberStatus::Active->value)->count(),
                'pending_invitations' => $this->invitationScope($complexId)->where('status', InvitationStatus::Pending->value)->where('expires_at', '>', now())->count(),
            ],
        ]]);
    }

    /** GET /v1/komendant/devices — the complex's shared devices (the monthly subscription price only; never the sale price). */
    public function devices(Request $request, SubscriptionPriceResolver $prices): JsonResponse
    {
        $manager = $this->manager($request, Permission::DEVICES_VIEW);
        $threshold = now()->subMinutes((int) config('domain.devices.offline_threshold_minutes', 15));
        $price = $prices->commercial(SubscriptionTier::Main);

        $data = $this->complexDevices((int) $manager->complex_id)->orderBy('location_label')->get()
            ->map(fn (Device $d): array => [
                'id' => (int) $d->id,
                'label' => $d->location_label ?? $d->serial,
                'address' => $d->address,
                'status' => $d->status->value,
                'online' => $d->last_online_at !== null && $d->last_online_at->greaterThan($threshold),
                'image_url' => $d->image_url,
                'subscription_price_minor' => $price['price_minor'],
                'subscription_term_days' => $price['term_days'],
                'currency' => $price['currency'],
            ])->all();

        return response()->json(['data' => $data]);
    }

    /** GET /v1/komendant/residents */
    public function residents(Request $request): JsonResponse
    {
        $manager = $this->manager($request, Permission::RESIDENTS_VIEW);
        $complexId = (int) $manager->complex_id;
        $deviceIds = $this->complexDevices($complexId)->pluck('id');

        $members = ComplexMember::query()->with('user')
            ->where('complex_id', $complexId)
            ->where('status', ComplexMemberStatus::Active->value)
            ->orderByDesc('joined_at')
            ->limit(500)
            ->get();

        $activeByUser = Subscription::query()
            ->join('device_users as du', 'du.id', '=', 'subscriptions.device_user_id')
            ->whereIn('du.device_id', $deviceIds)
            ->whereIn('du.user_id', $members->pluck('user_id'))
            ->where('du.status', 'active')
            ->where('subscriptions.status', SubscriptionStatus::Active->value)
            ->where('subscriptions.ends_at', '>', now())
            ->selectRaw('du.user_id, count(*) as c')
            ->groupBy('du.user_id')
            ->pluck('c', 'user_id');

        $data = $members->map(fn (ComplexMember $m): array => [
            'user_id' => (int) $m->user_id,
            'full_name' => $m->user?->full_name,
            'email' => $m->user?->email,
            'phone_masked' => PhoneNumber::tryFromInput((string) $m->user?->phone)?->masked() ?? $m->user?->phone,
            'joined_at' => optional($m->joined_at)->toIso8601String(),
            'active_subscriptions' => (int) ($activeByUser[$m->user_id] ?? 0),
        ])->all();

        return response()->json(['data' => $data]);
    }

    /** DELETE /v1/komendant/residents/{userId} — remove from the complex; access revoked, subscriptions cancelled (no refund). */
    public function removeResident(Request $request, int $userId, RemoveComplexResident $action): JsonResponse
    {
        $manager = $this->manager($request, Permission::RESIDENTS_DELETE);
        $resident = User::query()->find($userId);
        $result = $resident !== null ? $action->handle((int) $manager->complex_id, $resident, $request->user(), $manager) : null;
        abort_if($result === null, 404);

        return response()->json(['data' => $result]);
    }

    /** GET /v1/komendant/invitations?status=pending|accepted|expired|cancelled */
    public function invitations(Request $request): JsonResponse
    {
        $manager = $this->manager($request, Permission::RESIDENTS_VIEW);
        $query = $this->invitationScope((int) $manager->complex_id)->orderByDesc('id')->limit(200);

        $status = (string) $request->query('status', '');
        if ($status === 'expired') {
            $query->where(fn ($q) => $q->where('status', InvitationStatus::Expired->value)
                ->orWhere(fn ($p) => $p->where('status', InvitationStatus::Pending->value)->where('expires_at', '<=', now())));
        } elseif ($status === 'pending') {
            $query->where('status', InvitationStatus::Pending->value)->where('expires_at', '>', now());
        } elseif (in_array($status, ['accepted', 'cancelled', 'declined'], true)) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->get()->map(fn (Invitation $i): array => $this->invitationArray($i))->all()]);
    }

    /** POST /v1/komendant/invitations {first_name, last_name, email} */
    public function invite(Request $request): JsonResponse
    {
        $manager = $this->manager($request, Permission::RESIDENTS_CREATE);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
        ]);
        $complexId = (int) $manager->complex_id;
        $email = mb_strtolower(trim($data['email']));

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing !== null && $this->memberships->isMember($complexId, (int) $existing->getKey())) {
            return $this->error(409, 'already_resident', 'Bu email artıq kompleksin sakinidir.');
        }

        try {
            ['invitation' => $invitation] = $this->invitations->createComplexResident($complexId, $manager, $email, $data['first_name'], $data['last_name']);
        } catch (InvitationException $e) {
            return $this->invitationError($e);
        }

        return response()->json(['data' => $this->invitationArray($invitation)], 201);
    }

    /** POST /v1/komendant/invitations/{id}/resend */
    public function resend(Request $request, int $id): JsonResponse
    {
        $manager = $this->manager($request, Permission::RESIDENTS_CREATE);
        $invitation = $this->invitationScope((int) $manager->complex_id)->findOrFail($id);

        try {
            $this->invitations->resend($invitation);
        } catch (InvitationException $e) {
            return $this->invitationError($e);
        }

        return response()->json(['data' => $this->invitationArray($invitation->refresh())]);
    }

    /** POST /v1/komendant/invitations/{id}/revoke */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $manager = $this->manager($request, Permission::RESIDENTS_DELETE);
        $invitation = $this->invitationScope((int) $manager->complex_id)->findOrFail($id);

        try {
            $this->invitations->revoke($invitation, $request->user(), $manager);
        } catch (InvitationException $e) {
            return $this->invitationError($e);
        }

        return response()->json(['data' => $this->invitationArray($invitation->refresh())]);
    }

    private function manager(Request $request, string $permission): AdminUser
    {
        /** @var AdminUser $manager */
        $manager = $request->attributes->get(EnsureKomendant::ATTR_MANAGER);
        abort_unless($manager instanceof AdminUser && $manager->hasPermission($permission), 403);

        return $manager;
    }

    private function complexDevices(int $complexId)
    {
        return Device::query()->where('complex_id', $complexId)->where('ownership_mode', DeviceOwnershipMode::Complex->value);
    }

    private function invitationScope(int $complexId)
    {
        return Invitation::query()->where('kind', InvitationKind::ComplexResident->value)->where('complex_id', $complexId);
    }

    /** @return array<string, mixed> */
    private function invitationArray(Invitation $i): array
    {
        $status = $i->status === InvitationStatus::Pending && $i->expires_at->lessThanOrEqualTo(now())
            ? InvitationStatus::Expired->value
            : $i->status->value;

        return [
            'id' => (int) $i->id,
            'first_name' => $i->invitee_first_name,
            'last_name' => $i->invitee_last_name,
            'email' => $i->invitee_email,
            'status' => $status,
            'expires_at' => optional($i->expires_at)->toIso8601String(),
            'send_count' => (int) $i->send_count,
            'last_sent_at' => optional($i->last_sent_at)->toIso8601String(),
            'accepted_at' => optional($i->accepted_at)->toIso8601String(),
            'revoked_at' => optional($i->revoked_at)->toIso8601String(),
            'created_at' => optional($i->created_at)->toIso8601String(),
        ];
    }

    private function invitationError(InvitationException $e): JsonResponse
    {
        return $this->error($e->status, $e->errorCode, $e->getMessage());
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        $requestId = Context::get('request_id');

        return response()->json(['error' => [
            'code' => $code,
            'message_key' => 'errors.'.$code,
            'message' => $message,
            'details' => null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]], $status);
    }
}
