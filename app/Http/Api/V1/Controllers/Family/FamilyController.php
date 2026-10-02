<?php

namespace App\Http\Api\V1\Controllers\Family;

use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\FamilyService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Support\Phone\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

/**
 * Family head surface (IMPLEMENTATION_PLAN §14 / B8). Everything is scoped to the CALLER as head: a member
 * sees nothing of other members, and inviting / granting is gated by DevicePolicy::manageFamily server-side
 * (private: the owner; complex: a resident on their own, non-family row). Payment uses the existing
 * POST /v1/orders (sub_additional), authorized by SubscriptionPaymentAuthorizer.
 */
class FamilyController
{
    public function __construct(
        private readonly FamilyService $family,
        private readonly InvitationService $invitations,
    ) {}

    /** GET /v1/family/members — the caller's active family members with their granted devices. */
    public function members(Request $request): JsonResponse
    {
        $links = FamilyLink::query()->with('member')
            ->where('head_user_id', $request->user()->getKey())
            ->where('status', FamilyLinkStatus::Active->value)
            ->orderBy('id')
            ->get();

        $rows = $this->rowsFor($links->modelKeys());

        return response()->json(['data' => $links->map(fn (FamilyLink $l): array => [
            'family_link_id' => (int) $l->id,
            'user_id' => (int) $l->member_user_id,
            'full_name' => $l->member?->full_name,
            'email' => $l->member?->email,
            'phone_masked' => PhoneNumber::tryFromInput((string) $l->member?->phone)?->masked(),
            'linked_at' => optional($l->linked_at)->toIso8601String(),
            'devices' => $rows->where('family_link_id', $l->id)->map(fn (DeviceUser $r): array => $this->deviceArray($r))->values()->all(),
        ])->all()]);
    }

    /** POST /v1/family/members {first_name, last_name, email, device_id} — same as POST /v1/devices/{id}/invitations. */
    public function inviteMember(Request $request): JsonResponse
    {
        $deviceId = (int) $request->validate(['device_id' => ['required', 'integer']])['device_id'];

        return $this->inviteFor($request, $deviceId);
    }

    /** POST /v1/devices/{deviceId}/invitations {first_name, last_name, email} */
    public function inviteForDevice(Request $request, int $deviceId): JsonResponse
    {
        return $this->inviteFor($request, $deviceId);
    }

    /** GET /v1/devices/{deviceId}/invitations — the caller's family invitations for this device. */
    public function deviceInvitations(Request $request, int $deviceId): JsonResponse
    {
        $device = $this->managedDevice($request, $deviceId);
        $list = $this->headInvitations($request)->where('device_id', $device->getKey())->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $list->map(fn (Invitation $i): array => $this->invitationArray($i))->all()]);
    }

    /** DELETE /v1/family/members/{userId} — link removed, granted access revoked, subscriptions cancelled (no refund). */
    public function removeMember(Request $request, int $userId): JsonResponse
    {
        $member = User::query()->find($userId);
        $result = $member !== null ? $this->family->removeMember($request->user(), $member) : null;
        abort_if($result === null, 404);

        return response()->json(['data' => $result]);
    }

    /** GET /v1/family/subscriptions — subscriptions of the caller's family members the caller may pay for. */
    public function subscriptions(Request $request): JsonResponse
    {
        $links = FamilyLink::query()->where('head_user_id', $request->user()->getKey())->where('status', FamilyLinkStatus::Active->value)->get();
        $rows = $this->rowsFor($links->modelKeys())->load('user');

        return response()->json(['data' => $rows->map(fn (DeviceUser $r): array => [
            'member' => ['user_id' => (int) $r->user_id, 'full_name' => $r->user?->full_name],
        ] + $this->deviceArray($r))->values()->all()]);
    }

    /** POST /v1/family/invitations/{id}/resend */
    public function resend(Request $request, int $id): JsonResponse
    {
        $invitation = $this->headInvitations($request)->findOrFail($id);
        try {
            $this->invitations->resend($invitation);
        } catch (InvitationException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => $this->invitationArray($invitation->refresh())]);
    }

    /** POST /v1/family/invitations/{id}/revoke */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $invitation = $this->headInvitations($request)->findOrFail($id);
        try {
            $this->invitations->revoke($invitation, $request->user());
        } catch (InvitationException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => $this->invitationArray($invitation->refresh())]);
    }

    private function inviteFor(Request $request, int $deviceId): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
        ]);
        $device = $this->managedDevice($request, $deviceId);

        try {
            $result = $this->family->invite($request->user(), $device, $data['email'], $data['first_name'], $data['last_name']);
        } catch (InvitationException $e) {
            return $this->error($e);
        }

        return $result['granted'] !== null
            ? response()->json(['data' => ['granted' => true, 'device' => $this->deviceArray($result['granted'])]], 201)
            : response()->json(['data' => ['granted' => false, 'invitation' => $this->invitationArray($result['invitation'])]], 201);
    }

    /** Visible device + family-head rights; a device the caller cannot see answers 404, a non-head 403. */
    private function managedDevice(Request $request, int $deviceId): Device
    {
        $device = Device::query()->find($deviceId);
        abort_unless($device !== null && $request->user()->can('view', $device), 404);
        abort_unless($request->user()->can('manageFamily', $device), 403);

        return $device;
    }

    private function headInvitations(Request $request)
    {
        return Invitation::query()->where('kind', InvitationKind::FamilyMember->value)->where('invited_by_user_id', $request->user()->getKey());
    }

    /** @param  array<int, int>  $linkIds */
    private function rowsFor(array $linkIds)
    {
        return DeviceUser::query()->with('device')
            ->whereIn('family_link_id', $linkIds === [] ? [0] : $linkIds)
            ->where('status', DeviceUserStatus::Active->value)
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function deviceArray(DeviceUser $row): array
    {
        $sub = Subscription::query()->where('device_user_id', $row->getKey())->first();

        return [
            'device_id' => (int) $row->device_id,
            'label' => $row->device?->location_label ?? $row->device?->serial,
            'subscription' => $sub === null ? null : [
                'id' => (int) $sub->id,
                'status' => $sub->status->value,
                'tier' => $sub->tier->value,
                'price_minor' => (int) $sub->price_minor,
                'term_days' => (int) $sub->term_days,
                'currency' => $sub->currency,
                'ends_at' => optional($sub->ends_at)->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function invitationArray(Invitation $i): array
    {
        $status = $i->status === InvitationStatus::Pending && $i->expires_at->lessThanOrEqualTo(now())
            ? InvitationStatus::Expired->value
            : $i->status->value;

        return [
            'id' => (int) $i->id,
            'device_id' => $i->device_id !== null ? (int) $i->device_id : null,
            'first_name' => $i->invitee_first_name,
            'last_name' => $i->invitee_last_name,
            'email' => $i->invitee_email,
            'status' => $status,
            'expires_at' => optional($i->expires_at)->toIso8601String(),
            'send_count' => (int) $i->send_count,
        ];
    }

    private function error(InvitationException $e): JsonResponse
    {
        $requestId = Context::get('request_id');

        return response()->json(['error' => [
            'code' => $e->errorCode, 'message_key' => 'errors.'.$e->errorCode, 'message' => $e->getMessage(), 'details' => null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]], $e->status);
    }
}
