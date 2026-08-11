<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\DeviceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceModel
 *
 * Maps to openapi components/schemas/DeviceModel.
 */
class DeviceModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'vendor' => $this->vendor,
            'model_code' => $this->model_code,
            'supports_clip' => (bool) $this->supports_clip,
            'supports_sms' => (bool) $this->supports_sms,
            'supports_mqtt' => (bool) $this->supports_mqtt,
            'default_driver_type' => $this->default_driver_type?->value,
            'whitelist_capacity' => $this->whitelist_capacity !== null ? (int) $this->whitelist_capacity : null,
            'sms_open_command' => $this->sms_open_command,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
