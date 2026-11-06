<?php

namespace App\Http\Admin\V1\Controllers\Subscriptions;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Models\Device;
use App\Domain\Subscriptions\Enums\SubscriptionPriceScope;
use App\Domain\Subscriptions\Models\SubscriptionPriceOverride;
use App\Domain\Users\Models\User;
use App\Http\Admin\V1\Requests\Subscriptions\SetSubscriptionPriceRequest;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Special subscription prices (subscriptions.manage: super admin and finance). A change applies from the next
 * payment: running subscriptions and orders already open keep their amount. Every change is audited.
 */
class AdminSubscriptionPriceController
{
    use AuthorizesAdmin;

    public function __construct(private readonly AuditLogger $audit) {}

    /** GET /admin/v1/subscription-prices — adminListSubscriptionPrices */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SUBSCRIPTIONS_MANAGE);

        $rows = SubscriptionPriceOverride::query()->latest('updated_at')->latest('id')->get();
        $labels = $this->labels($rows);

        return response()->json(['data' => $rows->map(fn (SubscriptionPriceOverride $row): array => [
            'id' => (int) $row->id,
            'scope' => $row->scope->value,
            'subject_id' => $row->subject_id,
            'subject_label' => $labels[$row->scope->value][$row->subject_id] ?? null,
            'price_minor' => $row->price_minor,
            'updated_at' => optional($row->updated_at)->toIso8601String(),
        ])->all()]);
    }

    /** PUT /admin/v1/subscription-prices — adminSetSubscriptionPrice: one row per person, device or complex. */
    public function upsert(SetSubscriptionPriceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $subject = ['scope' => $data['scope'], 'subject_id' => (int) $data['subject_id']];

        $before = SubscriptionPriceOverride::query()->where($subject)->value('price_minor');
        $row = SubscriptionPriceOverride::query()->updateOrCreate($subject, [
            'price_minor' => (int) $data['price_minor'],
            'updated_by_admin_id' => $actor instanceof AdminUser ? (int) $actor->getKey() : null,
        ]);

        $this->audit->record('subscription.price_set', $subject + [
            'old_price_minor' => $before !== null ? (int) $before : null,
            'new_price_minor' => (int) $data['price_minor'],
        ], SubscriptionPriceOverride::class, (int) $row->id);

        return response()->json(['data' => ['id' => (int) $row->id] + $subject + ['price_minor' => $row->price_minor]]);
    }

    /** DELETE /admin/v1/subscription-prices/{id} — adminRemoveSubscriptionPrice: back to the standard price. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::SUBSCRIPTIONS_MANAGE);

        $row = SubscriptionPriceOverride::query()->findOrFail($id);
        $row->delete();

        $this->audit->record('subscription.price_removed', [
            'scope' => $row->scope->value,
            'subject_id' => $row->subject_id,
            'old_price_minor' => $row->price_minor,
        ], SubscriptionPriceOverride::class, $id);

        return response()->json(null, 204);
    }

    /**
     * @param  Collection<int, SubscriptionPriceOverride>  $rows
     * @return array<string, array<int, string>>
     */
    private function labels(Collection $rows): array
    {
        $ids = fn (SubscriptionPriceScope $scope): array => $rows->filter(fn ($r): bool => $r->scope === $scope)->pluck('subject_id')->all();

        return [
            'user' => User::query()->whereKey($ids(SubscriptionPriceScope::User))->get()
                ->mapWithKeys(fn (User $u): array => [(int) $u->id => (string) ($u->full_name ?: $u->phone ?: $u->email ?: '#'.$u->id)])->all(),
            'device' => Device::query()->whereKey($ids(SubscriptionPriceScope::Device))->get()
                ->mapWithKeys(fn (Device $d): array => [(int) $d->id => (string) ($d->location_label ?: $d->serial)])->all(),
            'complex' => Complex::query()->whereKey($ids(SubscriptionPriceScope::Complex))->pluck('name', 'id')->all(),
        ];
    }
}
