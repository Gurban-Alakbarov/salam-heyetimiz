<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\SimOperator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SimOperator
 *
 * Maps to openapi components/schemas/SimOperator.
 */
class SimOperatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'country_iso' => $this->country_iso,
            'mcc_mnc' => $this->mcc_mnc,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
