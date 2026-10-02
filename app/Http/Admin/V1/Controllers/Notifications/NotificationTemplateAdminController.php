<?php

namespace App\Http\Admin\V1\Controllers\Notifications;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Models\NotificationTemplateLocale;
use App\Domain\Notifications\Services\TemplateRenderer;
use App\Domain\Notifications\Support\TemplatePlaceholders;
use App\Http\Concerns\AuthorizesAdmin;
use App\Support\Enums\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Notification template editor (IMPLEMENTATION_PLAN §17 / B10, BR-14). Admins edit the subject + body of the
 * EXISTING templates per locale (az/en/ru). Copy lives only in notification_template_locales and is read
 * fresh by TemplateRenderer on every dispatch, so a saved edit is what the next push / inbox entry uses — the
 * dispatcher and FCM path are untouched. Placeholders are whitelisted per template (unknown `{x}` → 422);
 * `system.admin_campaign` is listed read-only. View: notifications.templates.view; edit:
 * notifications.templates.manage. Every edit is audited with the old and new copy.
 */
class NotificationTemplateAdminController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /admin/v1/notification-templates */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::NOTIFICATION_TEMPLATES_VIEW);

        $templates = NotificationTemplate::query()->with('locales')->orderBy('template_key')->get();

        return response()->json(['data' => $templates->map(function (NotificationTemplate $t): array {
            $have = $t->locales->pluck('locale')->all();

            return $this->summary($t) + [
                'locales' => array_map(fn (string $l): array => ['locale' => $l, 'present' => in_array($l, $have, true)], Locale::values()),
                'locales_complete' => array_diff(Locale::values(), $have) === [],
            ];
        })->all()]);
    }

    /** GET /admin/v1/notification-templates/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::NOTIFICATION_TEMPLATES_VIEW);
        $template = NotificationTemplate::query()->with('locales')->findOrFail($id);

        return response()->json(['data' => $this->summary($template) + [
            'locales' => array_map(function (string $l) use ($template): array {
                /** @var NotificationTemplateLocale|null $row */
                $row = $template->locales->firstWhere('locale', $l);

                return [
                    'locale' => $l,
                    'subject' => $row?->subject,
                    'body' => $row?->body,
                    'updated_by_admin_id' => $row?->updated_by_admin_id !== null ? (int) $row->updated_by_admin_id : null,
                    'updated_at' => optional($row?->updated_at)->toIso8601String(),
                ];
            }, Locale::values()),
        ]]);
    }

    /** PUT /admin/v1/notification-templates/{id}/locales/{locale} {subject, body} */
    public function updateLocale(Request $request, int $id, string $locale): JsonResponse
    {
        $admin = $this->requirePermission($request, Permission::NOTIFICATION_TEMPLATES_MANAGE);
        $template = NotificationTemplate::query()->findOrFail($id);
        abort_unless(Locale::tryFrom($locale) !== null, 404);
        if (TemplatePlaceholders::isReadOnly($template->template_key)) {
            return $this->error(409, 'template_read_only', 'Bu şablonun mətni hər kampaniyada ayrıca verilir və burada redaktə olunmur.');
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'min:1', 'max:120'],
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);
        $this->assertPlaceholders($template->template_key, $data['subject'], $data['body']);

        $row = DB::transaction(function () use ($template, $locale, $data, $admin): NotificationTemplateLocale {
            /** @var NotificationTemplateLocale|null $row */
            $row = NotificationTemplateLocale::query()
                ->where('notification_template_id', $template->getKey())->where('locale', $locale)
                ->lockForUpdate()->first();
            $old = $row !== null ? ['subject' => $row->subject, 'body' => $row->body] : null;

            $row ??= new NotificationTemplateLocale(['notification_template_id' => $template->getKey(), 'locale' => $locale]);
            $row->forceFill(['subject' => $data['subject'], 'body' => $data['body'], 'updated_by_admin_id' => $admin->getKey()])->save();

            $this->audit->record('notification.template_updated', [
                'template_id' => (int) $template->getKey(),
                'template_key' => $template->template_key,
                'locale' => $locale,
                'old' => $old,
                'new' => ['subject' => $data['subject'], 'body' => $data['body']],
                'admin_id' => (int) $admin->getKey(),
            ], NotificationTemplate::class, (int) $template->getKey());

            return $row;
        });

        return response()->json(['data' => [
            'template_id' => (int) $template->getKey(),
            'locale' => $locale,
            'subject' => $row->subject,
            'body' => $row->body,
            'updated_by_admin_id' => (int) $row->updated_by_admin_id,
            'updated_at' => optional($row->updated_at)->toIso8601String(),
        ]]);
    }

    /**
     * POST /admin/v1/notification-templates/{id}/preview {locale?, subject?, body?} — renders the given draft
     * (or the stored copy) with sample variables; nothing is saved or sent.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::NOTIFICATION_TEMPLATES_VIEW);
        $template = NotificationTemplate::query()->with('locales')->findOrFail($id);
        if (TemplatePlaceholders::isReadOnly($template->template_key)) {
            return $this->error(409, 'template_read_only', 'Bu şablonun mətni hər kampaniyada ayrıca verilir.');
        }

        $data = $request->validate([
            'locale' => ['sometimes', 'string', 'in:'.implode(',', Locale::values())],
            'subject' => ['sometimes', 'nullable', 'string', 'max:120'],
            'body' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        $locale = $data['locale'] ?? Locale::default()->value;
        $stored = $template->locales->firstWhere('locale', $locale);
        $subject = (string) ($data['subject'] ?? $stored?->subject ?? '');
        $body = (string) ($data['body'] ?? $stored?->body ?? '');
        $this->assertPlaceholders($template->template_key, $subject, $body);

        $vars = TemplatePlaceholders::samples($template->template_key);

        return response()->json(['data' => [
            'locale' => $locale,
            'title' => $this->renderer->interpolate($subject, $vars),
            'body' => $this->renderer->interpolate($body, $vars),
            'sample_variables' => $vars,
        ]]);
    }

    /** @return array<string, mixed> */
    private function summary(NotificationTemplate $t): array
    {
        return [
            'id' => (int) $t->id,
            'template_key' => $t->template_key,
            'category' => $t->category->value,
            'channels_mask' => (int) $t->default_channels_mask,
            'is_active' => (bool) $t->is_active,
            'read_only' => TemplatePlaceholders::isReadOnly($t->template_key),
            'placeholders' => TemplatePlaceholders::allowed($t->template_key),
        ];
    }

    private function assertPlaceholders(string $templateKey, string $subject, string $body): void
    {
        $unknown = TemplatePlaceholders::unknown($templateKey, $subject.' '.$body);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => 'Naməlum dəyişən(lər): '.implode(', ', array_map(fn ($u) => '{'.$u.'}', $unknown))
                    .'. İcazəli: '.(TemplatePlaceholders::allowed($templateKey) === [] ? 'yoxdur' : implode(', ', array_map(fn ($a) => '{'.$a.'}', TemplatePlaceholders::allowed($templateKey)))),
            ]);
        }
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        $requestId = Context::get('request_id');

        return response()->json(['error' => [
            'code' => $code, 'message_key' => 'errors.'.$code, 'message' => $message, 'details' => null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]], $status);
    }
}
