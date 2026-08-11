<?php

namespace App\Http\Admin\V1\Controllers\Admins;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lists complexes for assignment UIs (any authenticated admin; complex_manager sees only its own). */
class ComplexController
{
    /** GET /admin/v1/complexes — adminListComplexes. */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof AdminUser, 401);

        $query = Complex::query()->where('is_active', true)->orderBy('name');
        $scope = $actor->complexScopeId();
        if ($scope !== null) {
            $query->whereKey($scope);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Complex $c): array => [
                'id' => (int) $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'region_id' => $c->region_id !== null ? (int) $c->region_id : null,
            ])->all(),
        ]);
    }
}
