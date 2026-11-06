<?php

namespace App\Http\Admin\V1\Controllers\Site;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Site\Enums\SiteLeadStatus;
use App\Domain\Site\Models\SiteLead;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Requests from the public website's contact form (applications.view / applications.manage). */
class AdminSiteLeadController
{
    use AuthorizesAdmin;

    public function __construct(private readonly AuditLogger $audit) {}

    /** GET /admin/v1/site-leads?status=&q= */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::APPLICATIONS_VIEW);
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $page = SiteLead::query()
            ->when(is_string($status) && SiteLeadStatus::tryFrom($status) !== null, fn ($b) => $b->where('status', $status))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn (SiteLead $lead): array => $this->present($lead))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** PATCH /admin/v1/site-leads/{id} {status, admin_note?} */
    public function update(Request $request, int $id): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::APPLICATIONS_MANAGE);
        $data = $request->validate([
            'status' => ['required', Rule::enum(SiteLeadStatus::class)],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $lead = SiteLead::query()->findOrFail($id);
        $before = $lead->status->value;
        $lead->forceFill([
            'status' => $data['status'],
            'admin_note' => array_key_exists('admin_note', $data) ? $data['admin_note'] : $lead->admin_note,
            'handled_by_admin_id' => (int) $admin->getKey(),
        ])->save();

        $this->audit->record('site_lead.updated', ['from' => $before, 'to' => $data['status']], SiteLead::class, (int) $lead->id);

        return response()->json(['data' => $this->present($lead->refresh())]);
    }

    /** @return array<string, mixed> */
    private function present(SiteLead $lead): array
    {
        return [
            'id' => (int) $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'place' => $lead->place,
            'topic' => $lead->topic,
            'message' => $lead->message,
            'locale' => $lead->locale,
            'status' => $lead->status->value,
            'admin_note' => $lead->admin_note,
            'created_at' => optional($lead->created_at)->toIso8601String(),
        ];
    }
}
