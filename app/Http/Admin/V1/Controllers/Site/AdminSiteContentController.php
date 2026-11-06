<?php

namespace App\Http\Admin\V1\Controllers\Site;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Site\Content\SiteDocumentService;
use App\Domain\Site\Content\TranslationState;
use App\Domain\Site\Models\SiteDocument;
use App\Domain\Site\Models\SiteRevision;
use App\Domain\Site\Publishing\SectionCatalog;
use App\Domain\Site\Publishing\SiteBuilder;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The public website's content: drafts, versions and the list of section types (site.manage). */
class AdminSiteContentController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly SiteDocumentService $documents,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /admin/v1/site/catalog — everything the panel needs to draw the forms. */
    public function catalog(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);

        return response()->json(['data' => [
            'languages' => SiteBuilder::LANGS,
            'section_types' => SectionCatalog::types(),
            'page_fields' => SectionCatalog::pageFields(),
            'shared_fields' => SectionCatalog::sharedFields(),
            'icons' => SiteDocumentService::iconNames(),
        ]]);
    }

    /** GET /admin/v1/site/documents */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);

        $documents = SiteDocument::query()->with('updatedBy')->orderBy('id')->get();

        return response()->json(['data' => $documents->map(fn (SiteDocument $d): array => $this->summary($d))->all()]);
    }

    /** GET /admin/v1/site/documents/{key} */
    public function show(Request $request, string $key): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);

        return response()->json(['data' => $this->full($this->find($key))]);
    }

    /** PUT /admin/v1/site/documents/{key} {content, version} */
    public function update(Request $request, string $key): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::SITE_MANAGE);
        $data = $request->validate([
            'content' => ['required', 'array'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $before = $data['version'];
        $document = $this->documents->save($key, $data['content'], $before, $admin);
        if ($document->version !== $before) {
            $this->audit->record('site_document.updated', ['key' => $key, 'version' => $document->version], SiteDocument::class, (int) $document->id);
        }

        return response()->json(['data' => $this->full($document->load('updatedBy'))]);
    }

    /** GET /admin/v1/site/documents/{key}/revisions */
    public function revisions(Request $request, string $key): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);
        $document = $this->find($key);

        $revisions = $document->revisions()->with('admin')->orderByDesc('version')->get();

        return response()->json(['data' => $revisions->map(fn (SiteRevision $r): array => [
            'version' => $r->version,
            'admin' => $r->admin?->name,
            'restored_from' => $r->restored_from,
            'published' => $r->version === $document->published_version,
            'created_at' => optional($r->created_at)->toIso8601String(),
        ])->all()]);
    }

    /** GET /admin/v1/site/documents/{key}/revisions/{version} */
    public function revision(Request $request, string $key, int $version): JsonResponse
    {
        $this->requirePermission($request, Permission::SITE_MANAGE);
        $revision = $this->find($key)->revisions()->with('admin')->where('version', $version)->firstOrFail();

        return response()->json(['data' => [
            'version' => $revision->version,
            'admin' => $revision->admin?->name,
            'content' => $revision->content,
            'created_at' => optional($revision->created_at)->toIso8601String(),
        ]]);
    }

    /** POST /admin/v1/site/documents/{key}/revisions/{version}/restore {version} — back to an earlier state, as a new draft. */
    public function restore(Request $request, string $key, int $version): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::SITE_MANAGE);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        $before = $data['version'];
        $document = $this->documents->restore($key, $version, $before, $admin);
        if ($document->version !== $before) {
            $this->audit->record('site_document.restored', ['key' => $key, 'version' => $document->version, 'from' => $version], SiteDocument::class, (int) $document->id);
        }

        return response()->json(['data' => $this->full($document->load('updatedBy'))]);
    }

    private function find(string $key): SiteDocument
    {
        return SiteDocument::query()->with('updatedBy')->where('key', $key)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function summary(SiteDocument $document): array
    {
        $issues = (new TranslationState)->issues($document->content);

        return [
            'key' => $document->key,
            'title' => $document->key === SiteDocument::SHARED ? 'Ümumi mətnlər' : ($document->content['title']['az'] ?? $document->key),
            'version' => $document->version,
            'published_version' => $document->published_version,
            'has_unpublished_changes' => $document->hasUnpublishedChanges(),
            'translations_missing' => count(array_filter($issues, fn (array $i) => $i['state'] === 'missing')),
            'translations_stale' => count(array_filter($issues, fn (array $i) => $i['state'] === 'stale')),
            'updated_by' => $document->updatedBy?->name,
            'updated_at' => optional($document->updated_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function full(SiteDocument $document): array
    {
        return [
            ...$this->summary($document),
            'content' => $document->content,
            'translation_issues' => (new TranslationState)->issues($document->content),
        ];
    }
}
