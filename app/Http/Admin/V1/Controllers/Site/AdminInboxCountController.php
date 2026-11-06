<?php

namespace App\Http\Admin\V1\Controllers\Site;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Applications\Enums\IndividualApplicationStatus;
use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Site\Enums\SiteLeadStatus;
use App\Domain\Site\Models\SiteLead;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /admin/v1/inbox-counts — what waits for someone: the red numbers in the sidebar (applications.view). */
class AdminInboxCountController
{
    use AuthorizesAdmin;

    public function __invoke(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::APPLICATIONS_VIEW);

        return response()->json(['data' => [
            'applications' => IndividualApplication::query()->where('status', IndividualApplicationStatus::New->value)->count()
                + LegalEntityApplication::query()->where('status', LegalEntityApplicationStatus::Pending->value)->count(),
            'site_leads' => SiteLead::query()->where('status', SiteLeadStatus::New->value)->count(),
        ]]);
    }
}
