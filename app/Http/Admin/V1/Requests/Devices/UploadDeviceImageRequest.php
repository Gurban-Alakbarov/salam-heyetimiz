<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a barrier-photo upload (multipart, field `image`). Admin-only (devices.update). Enforces
 * BOTH the file extension (mimes) and the real MIME type (mimetypes), a true-image check, and the size cap.
 */
class UploadDeviceImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::DEVICES_UPDATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<string, mixed> $cfg */
        $cfg = config('domain.devices.image');

        return [
            'image' => [
                'required',
                'file',
                'image', // getimagesize()-backed: rejects non-images even with a spoofed extension
                'mimes:'.implode(',', $cfg['accept_ext']),        // extension check (jpg,jpeg,png,webp)
                'mimetypes:'.implode(',', $cfg['accept_mimes']),  // real MIME check (image/jpeg,png,webp)
                'max:'.(int) $cfg['max_kilobytes'],               // 5 MB
            ],
        ];
    }
}
