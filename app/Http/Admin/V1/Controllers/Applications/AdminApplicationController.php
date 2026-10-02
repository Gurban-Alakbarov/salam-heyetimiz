<?php

namespace App\Http\Admin\V1\Controllers\Applications;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Applications\Enums\IndividualApplicationStatus;
use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Applications\Exceptions\ApplicationException;
use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Applications\Services\ApplicationService;
use App\Domain\Applications\Services\LegalEntityApplicationReview;
use App\Domain\Applications\Support\ApplicationPresenter;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\Rule;

/**
 * Admin workflow for registration applications (IMPLEMENTATION_PLAN §10–§11 / B9). Separate tabs/endpoints
 * per type. applications.view to read; applications.manage to move a physical application through its
 * pipeline; approving a legal application additionally needs complexes.manage (it creates a complex).
 */
class AdminApplicationController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly ApplicationService $applications,
        private readonly LegalEntityApplicationReview $review,
    ) {}

    /** GET /admin/v1/applications/individual?status=&q= */
    public function individualIndex(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::APPLICATIONS_VIEW);
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $page = IndividualApplication::query()
            ->when(is_string($status) && IndividualApplicationStatus::tryFrom($status) !== null, fn ($b) => $b->where('status', $status))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('full_name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn ($a) => ApplicationPresenter::individual($a, true))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** GET /admin/v1/applications/individual/{id} */
    public function individualShow(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::APPLICATIONS_VIEW);

        return response()->json(['data' => ApplicationPresenter::individual(IndividualApplication::query()->findOrFail($id), true)]);
    }

    /** PATCH /admin/v1/applications/individual/{id} {status, admin_note?, rejection_reason?, device_id?} */
    public function individualUpdate(Request $request, int $id): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::APPLICATIONS_MANAGE);
        $v = $request->validate([
            'status' => ['required', Rule::enum(IndividualApplicationStatus::class)],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'min:3', 'max:500'],
            'device_id' => ['sometimes', 'nullable', 'integer', 'exists:devices,id'],
        ]);
        $application = IndividualApplication::query()->findOrFail($id);

        return $this->run(fn () => response()->json(['data' => ApplicationPresenter::individual($this->applications->changeIndividualStatus(
            $application,
            IndividualApplicationStatus::from($v['status']),
            $admin,
            $v['admin_note'] ?? null,
            $v['rejection_reason'] ?? null,
            isset($v['device_id']) ? (int) $v['device_id'] : null,
        ), true)]));
    }

    /** GET /admin/v1/applications/legal?status=&q= */
    public function legalIndex(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::APPLICATIONS_VIEW);
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $page = LegalEntityApplication::query()
            ->when(is_string($status) && LegalEntityApplicationStatus::tryFrom($status) !== null, fn ($b) => $b->where('status', $status))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('complex_name', 'like', "%{$q}%")->orWhere('legal_name', 'like', "%{$q}%")->orWhere('voen', 'like', "%{$q}%")))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn ($a) => ApplicationPresenter::legal($a, true))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** GET /admin/v1/applications/legal/{id} */
    public function legalShow(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::APPLICATIONS_VIEW);

        return response()->json(['data' => ApplicationPresenter::legal(LegalEntityApplication::query()->findOrFail($id), true)]);
    }

    /** POST /admin/v1/applications/legal/{id}/approve {region_id?} — creates the complex (one transaction). */
    public function legalApprove(Request $request, int $id): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::APPLICATIONS_MANAGE);
        $this->requirePermission($request, Permission::COMPLEXES_MANAGE);
        $v = $request->validate(['region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id']]);
        $application = LegalEntityApplication::query()->findOrFail($id);

        return $this->run(function () use ($application, $admin, $v): JsonResponse {
            $complex = $this->review->approve($application, $admin, isset($v['region_id']) ? (int) $v['region_id'] : null);

            return response()->json(['data' => ApplicationPresenter::legal($application->refresh(), true) + [
                'complex' => ['id' => (int) $complex->id, 'code' => $complex->code, 'name' => $complex->name],
            ]]);
        });
    }

    /** POST /admin/v1/applications/legal/{id}/reject {reason} */
    public function legalReject(Request $request, int $id): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::APPLICATIONS_MANAGE);
        $v = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $application = LegalEntityApplication::query()->findOrFail($id);

        return $this->run(fn () => response()->json(['data' => ApplicationPresenter::legal($this->review->reject($application, $admin, $v['reason']), true)]));
    }

    private function run(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (ApplicationException $e) {
            $requestId = Context::get('request_id');

            return response()->json(['error' => [
                'code' => $e->errorCode, 'message_key' => 'errors.'.$e->errorCode, 'message' => $e->getMessage(), 'details' => null,
                'request_id' => is_string($requestId) ? $requestId : null,
            ]], $e->status);
        }
    }
}
