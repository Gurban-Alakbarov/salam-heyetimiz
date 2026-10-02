<?php

namespace App\Http\Api\V1\Controllers\Applications;

use App\Domain\Applications\Exceptions\ApplicationException;
use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Applications\Services\ApplicationService;
use App\Domain\Applications\Support\ApplicationPresenter;
use App\Domain\Applications\Support\ApplicationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

/**
 * The applicant's own registration applications (IMPLEMENTATION_PLAN §10–§11 / B9). Physical and legal are
 * separate endpoints / models. A user sees and edits ONLY their own applications (others → 404), and edits only
 * while the application is still in its initial status.
 */
class ApplicationController
{
    public function __construct(private readonly ApplicationService $applications) {}

    /** POST /v1/applications/individual */
    public function storeIndividual(Request $request): JsonResponse
    {
        $data = $request->validate(ApplicationRules::individual());

        return $this->run(fn () => response()->json(['data' => ApplicationPresenter::individual($this->applications->submitIndividual($request->user(), $data))], 201));
    }

    /** POST /v1/applications/legal */
    public function storeLegal(Request $request): JsonResponse
    {
        $data = $request->validate(ApplicationRules::legal());

        return $this->run(fn () => response()->json(['data' => ApplicationPresenter::legal($this->applications->submitLegal($request->user(), $data))], 201));
    }

    /** PUT /v1/applications/individual/{id} — only while `new`. */
    public function updateIndividual(Request $request, int $id): JsonResponse
    {
        $application = IndividualApplication::query()->where('user_id', $request->user()->getKey())->findOrFail($id);
        $data = $request->validate(ApplicationRules::individual());

        return $this->run(fn () => response()->json(['data' => ApplicationPresenter::individual($this->applications->updateIndividual($application, $data))]));
    }

    /** PUT /v1/applications/legal/{id} — only while `pending`. */
    public function updateLegal(Request $request, int $id): JsonResponse
    {
        $application = LegalEntityApplication::query()->where('applicant_user_id', $request->user()->getKey())->findOrFail($id);
        $data = $request->validate(ApplicationRules::legal());

        return $this->run(fn () => response()->json(['data' => ApplicationPresenter::legal($this->applications->updateLegal($application, $data))]));
    }

    /** GET /v1/applications/mine */
    public function mine(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        return response()->json(['data' => [
            'account_type' => $request->user()->getAttributes()['account_type'] ?? null,
            'individual' => IndividualApplication::query()->where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($a) => ApplicationPresenter::individual($a))->all(),
            'legal' => LegalEntityApplication::query()->where('applicant_user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($a) => ApplicationPresenter::legal($a))->all(),
        ]]);
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
