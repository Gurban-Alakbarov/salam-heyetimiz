<?php

namespace App\Domain\Devices\Services;

use App\Domain\Devices\Models\Device;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Barrier photo storage. Uploaded images are optimised (downscaled to a max dimension and
 * re-encoded to WebP when GD is available; the validated original is kept otherwise), written to the
 * `public` disk under a random UUID name, and the resulting public URL is stored in `devices.image_url`.
 *
 * Backward compatible: `image_url` may still hold an EXTERNAL URL (older records) — those are served
 * as-is and are never deleted here. Only files we wrote (…/storage/devices/…) are ever removed.
 */
final class DeviceImageService
{
    /**
     * Store a new image for the device, replace (delete) the previous local file, and persist the URL.
     */
    public function store(UploadedFile $file, Device $device): string
    {
        $cfg = config('domain.devices.image');

        [$bytes, $ext] = $this->process($file);
        $path = trim((string) $cfg['dir'], '/').'/'.Str::uuid()->toString().'.'.$ext;

        Storage::disk($cfg['disk'])->put($path, $bytes, 'public');

        // Remove the previous file first — but only if it was one of ours (never an external URL).
        $this->purgeFile($device->image_url);

        $url = $this->publicUrl($path);
        $device->forceFill(['image_url' => $url])->save();

        return $url;
    }

    /** Remove the device's current image (file + column). External URLs only clear the column. */
    public function deleteFor(Device $device): void
    {
        $this->purgeFile($device->image_url);
        $device->forceFill(['image_url' => null])->save();
    }

    /** Delete just the stored file for a URL (used when the device row itself is deleted). */
    public function purgeFile(?string $url): void
    {
        $relative = $this->localRelativePath($url);
        if ($relative !== null) {
            Storage::disk(config('domain.devices.image.disk'))->delete($relative);
        }
    }

    /**
     * Optimise the upload. Returns [bytes, extension]. Downscales to the configured max dimension and
     * re-encodes to WebP when GD+WebP are present; otherwise returns the validated original untouched.
     *
     * @return array{0: string, 1: string}
     */
    private function process(UploadedFile $file): array
    {
        $cfg = config('domain.devices.image');
        $original = (string) file_get_contents($file->getRealPath());

        if ($original !== ''
            && extension_loaded('gd')
            && function_exists('imagecreatefromstring')
            && function_exists('imagewebp')) {
            try {
                $img = @imagecreatefromstring($original);
                if ($img !== false) {
                    $max = (int) $cfg['max_dimension'];
                    $w = imagesx($img);
                    $h = imagesy($img);
                    $longest = max($w, $h);
                    if ($longest > $max && $longest > 0) {
                        $scale = $max / $longest;
                        $resized = imagescale($img, (int) round($w * $scale), (int) round($h * $scale));
                        if ($resized !== false) {
                            imagedestroy($img);
                            $img = $resized;
                        }
                    }
                    imagepalettetotruecolor($img);
                    imagealphablending($img, false);
                    imagesavealpha($img, true);

                    ob_start();
                    imagewebp($img, null, (int) $cfg['webp_quality']);
                    $out = (string) ob_get_clean();
                    imagedestroy($img);

                    if ($out !== '') {
                        return [$out, 'webp'];
                    }
                }
            } catch (\Throwable) {
                // fall through to storing the original
            }
        }

        // Fallback (no GD/WebP, or a decode failure): keep the validated original.
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'img'));

        return [$original, $ext];
    }

    private function publicUrl(string $path): string
    {
        $base = config('domain.devices.image.base_url') ?: config('app.url');

        return rtrim((string) $base, '/').'/storage/'.ltrim($path, '/');
    }

    /**
     * If the URL points at a file WE stored (…/storage/<dir>/<name>), return its disk-relative path
     * (`<dir>/<name>`); otherwise null (external URLs, or anything with a traversal attempt).
     */
    private function localRelativePath(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $dir = trim((string) config('domain.devices.image.dir'), '/');
        $marker = '/storage/'.$dir.'/';
        $pos = strpos($url, $marker);
        if ($pos === false) {
            return null;
        }

        $name = basename(substr($url, $pos + strlen($marker)));
        if ($name === '' || str_contains($name, '..') || str_contains($name, '/')) {
            return null;
        }

        return $dir.'/'.$name;
    }
}
