<?php

namespace App\Http\Admin\V1\Controllers\Complexes;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Roster\Actions\RemoveComplexResident;
use App\Domain\Roster\Services\ComplexFamilyService;
use App\Domain\Users\Models\User;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O7 — the admin panel's side of the Komendant's resident / family actions on one complex, the SAME domain
 * actions as the mobile Komendant surface (RemoveComplexResident, ComplexFamilyService). A complex_manager is
 * locked to its own complex (others → 404); global admins may act on any complex.
 */
class AdminComplexFamilyController
{
    use AuthorizesAdmin;

    /**
     * DELETE /admin/v1/complexes/{complexId}/members/{userId} — adminRemoveComplexMember (residents.delete).
     * Removes a resident or a family member from the complex (the account stays). A family head needs
     * `family_action` = new_head (+ new_head_user_id) | remove_family, else 409 family_decision_required.
     */
    public function remove(Request $request, int $complexId, int $userId, RemoveComplexResident $action): JsonResponse
    {
        $manager = $this->requirePermission($request, Permission::RESIDENTS_DELETE);
        $this->assertComplex($request, $complexId);
        $choice = $request->validate([
            'family_action' => ['sometimes', 'nullable', 'string', 'in:new_head,remove_family'],
            'new_head_user_id' => ['required_if:family_action,new_head', 'nullable', 'integer'],
        ]);
        $user = User::query()->findOrFail($userId);

        $result = $action->handle(
            $complexId, $user, $this->linkedUser($manager), $manager,
            $choice['family_action'] ?? null,
            isset($choice['new_head_user_id']) ? (int) $choice['new_head_user_id'] : null,
        );
        abort_if($result === null, 404);

        return response()->json(['data' => ['complex_id' => $complexId] + $result]);
    }

    /**
     * POST /admin/v1/complexes/{complexId}/families/{headUserId}/head {new_head_user_id, old_head?: member|remove}
     * — adminChangeFamilyHead (residents.update). A member takes the family over at once.
     */
    public function changeHead(Request $request, int $complexId, int $headUserId, ComplexFamilyService $family): JsonResponse
    {
        $manager = $this->requirePermission($request, Permission::RESIDENTS_UPDATE);
        $this->assertComplex($request, $complexId);
        $data = $request->validate([
            'new_head_user_id' => ['required', 'integer'],
            'old_head' => ['sometimes', 'string', 'in:member,remove'],
        ]);
        $head = User::query()->findOrFail($headUserId);
        $newHead = User::query()->findOrFail((int) $data['new_head_user_id']);

        $result = $family->changeHead($complexId, $head, $newHead, ($data['old_head'] ?? 'member') === 'remove', $manager, $this->linkedUser($manager));

        return response()->json(['data' => $result]);
    }

    private function assertComplex(Request $request, int $complexId): void
    {
        $scope = $this->complexScopeId($request);
        abort_if($scope !== null && $scope !== $complexId, 404);
        Complex::query()->findOrFail($complexId);
    }

    /** The manager's linked mobile account (B5), if any — recorded as the acting user. */
    private function linkedUser(AdminUser $manager): ?User
    {
        $userId = AdminUser::query()->whereKey($manager->getKey())->value('user_id');

        return $userId !== null ? User::query()->find($userId) : null;
    }
}
