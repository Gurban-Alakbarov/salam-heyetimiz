<?php

namespace App\Http\Admin\V1\Controllers\Site;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Site\Models\SiteRelease;
use App\Domain\Site\Publishing\SitePublisher;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Publishing the website: what is waiting, the live copy, earlier copies and preview (site.manage). */
class AdminSitePublishController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly SitePublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /admin/v1/site/publish */
    public function status(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);

        return response()->json(['data' => $this->publisher->status()]);
    }

    /** POST /admin/v1/site/publish */
    public function publish(Request $request): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::SITE_MANAGE);

        $release = $this->publisher->publish($admin);
        $this->audit->record('site.published', ['pages' => $release->pages], SiteRelease::class, (int) $release->id);

        return response()->json(['data' => $this->publisher->status()]);
    }

    /** POST /admin/v1/site/releases/{id}/rollback */
    public function rollback(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);

        $release = SiteRelease::query()->findOrFail($id);
        $this->publisher->rollback($release);
        $this->audit->record('site.rolled_back', [], SiteRelease::class, (int) $release->id);

        return response()->json(['data' => $this->publisher->status()]);
    }

    /** GET /admin/v1/site/preview/{key}?lang=az */
    public function preview(Request $request, string $key): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);
        $data = $request->validate(['lang' => ['sometimes', Rule::in(['az', 'en', 'ru'])]]);

        return response()->json(['data' => ['html' => $this->publisher->preview($key, $data['lang'] ?? 'az')]]);
    }
}
