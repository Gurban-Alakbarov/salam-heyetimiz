<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Maps to openapi components/schemas/DeviceAdminDetail (DeviceAdmin + roster users + metadata).
 * The `stats` block is open-command-derived (DeviceComm, batch 09) and is omitted here. The roster
 * `users` are attached/eager-loaded by the controller, each carrying its subscription brief.
 */
class DeviceAdminDetailResource extends DeviceAdminResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'users' => $this->relationLoaded('deviceUsers')
                ? DeviceUserResource::collection($this->deviceUsers)->toArray($request)
                : [],
            'metadata' => $this->metadata ?? new \stdClass(),
        ]);
    }
}
