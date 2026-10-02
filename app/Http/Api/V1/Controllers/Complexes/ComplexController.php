<?php

namespace App\Http\Api\V1\Controllers\Complexes;

use App\Domain\Admin\Models\Complex;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Queries\ComplexDeviceQuery;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Subscriptions\Actions\StartDeviceSubscription;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Support\SubscriptionPriceResolver;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Resident complex browse + subscribe (IMPLEMENTATION_PLAN §13 / B7). Only the complexes the caller is an
 * active resident of are visible (ComplexPolicy::view; others → 404). Devices list the shared complex-mode
 * devices with the monthly subscription price and the CALLER's own status — never the sale price, never other
 * residents' data.
 */
class ComplexController
{
    public function __construct(
        private readonly ComplexMembershipService $memberships,
        private readonly ComplexDeviceQuery $devices,
        private readonly SubscriptionPriceResolver $prices,
    ) {}

    /** GET /v1/complexes */
    public function index(Request $request): JsonResponse
    {
        $ids = $this->memberships->complexIdsFor((int) $request->user()->getKey());
        $complexes = $ids === [] ? collect() : Complex::query()->whereIn('id', $ids)->orderBy('name')->get();

        return response()->json(['data' => $complexes->map(fn (Complex $c): array => $this->complexArray($c))->values()->all()]);
    }

    /** GET /v1/complexes/{complexId} */
    public function show(Request $request, int $complexId): JsonResponse
    {
        return response()->json(['data' => $this->complexArray($this->visible($request, $complexId))]);
    }

    /** GET /v1/complexes/{complexId}/devices */
    public function devices(Request $request, int $complexId): JsonResponse
    {
        $complex = $this->visible($request, $complexId);
        $price = $this->prices->commercial(SubscriptionTier::Main);

        $data = $this->devices->forComplex((int) $complex->getKey(), (int) $request->user()->getKey())
            ->map(fn (Device $d): array => [
                'id' => (int) $d->id,
                'label' => $d->location_label ?? $d->serial,
                'address' => $d->address,
                'image_url' => $d->image_url,
                'subscription_price_minor' => $price['price_minor'],
                'subscription_term_days' => $price['term_days'],
                'currency' => $price['currency'],
                'my_subscription_status' => $d->caller_subscription_status ?? 'none',
            ])->values()->all();

        return response()->json(['data' => $data]);
    }

    /** POST /v1/complexes/{complexId}/devices/{deviceId}/subscribe — Idempotency-Key required. */
    public function subscribe(Request $request, int $complexId, int $deviceId, StartDeviceSubscription $action): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            throw ValidationException::withMessages(['Idempotency-Key' => __('errors.idempotency_key_required')]);
        }
        $this->visible($request, $complexId);
        $returnUrl = $request->validate(['return_url' => ['sometimes', 'nullable', 'url', 'max:500']])['return_url'] ?? null;

        $order = $action->handle($request->user(), $complexId, $deviceId, $key, $returnUrl);

        // Same contract as createOrder: the checkout URL is the order's bank_redirect_url.
        return (new OrderResource($order->load('items')))->response()->setStatusCode(201);
    }

    private function visible(Request $request, int $complexId): Complex
    {
        $complex = Complex::query()->find($complexId);
        abort_unless($complex !== null && $request->user()->can('view', $complex), 404);

        return $complex;
    }

    /** @return array<string, mixed> */
    private function complexArray(Complex $c): array
    {
        return ['id' => (int) $c->id, 'name' => $c->name, 'address' => $c->address];
    }
}
