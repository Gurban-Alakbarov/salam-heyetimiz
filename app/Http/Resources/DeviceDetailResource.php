<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Maps to openapi components/schemas/DeviceDetail (Device + device_model + subscription).
 * `cooldown_seconds_remaining` is the open-cooldown state owned by DeviceComm (batch 09); it is
 * null here. The caller's `subscription` brief is attached to the model by the controller.
 */
class DeviceDetailResource extends DeviceResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'device_model' => $this->relationLoaded('model') && $this->model !== null
                ? ['vendor' => $this->model->vendor, 'model_code' => $this->model->model_code]
                : null,
            'cooldown_seconds_remaining' => null,
            'subscription' => $this->subscription_brief !== null
                ? (new SubscriptionBriefResource($this->subscription_brief))->toArray($request)
                : null,
        ]);
    }
}
