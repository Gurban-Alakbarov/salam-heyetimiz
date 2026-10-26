<?php

namespace App\Http\Api\V1\Controllers\Auth;

use App\Domain\Roster\Actions\DeleteResident;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Y7 — the account owner deletes their own account from the app (App Store 5.1.1(v) / Google Play account
 * deletion requirement). Same removal as an admin deletion (DeleteResident, self-service): access, family,
 * memberships and live subscriptions end (no refund), sessions are revoked and the personal data is erased
 * at once. An explicit `confirm: true` guards against an accidental call. A device owner is refused with
 * 409 successor_required until the device is transferred.
 */
class AccountDeletionController
{
    /** DELETE /v1/me — deleteMyAccount (204). */
    public function destroy(Request $request, DeleteResident $action): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);

        /** @var User $user */
        $user = $request->user();
        $action->handle($user, null, selfService: true);

        return response()->json(null, 204);
    }
}
