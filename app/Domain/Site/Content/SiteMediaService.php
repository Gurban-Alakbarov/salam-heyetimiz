<?php

namespace App\Domain\Site\Content;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Images for the website. Each upload is redrawn as a web-sized WebP: phone photos shrink to what a page needs, and
 * whatever the original file carried (GPS position, hidden payloads) does not reach the public site.
 *
 * Files live on the public disk so the panel can show them before publishing; the build copies them to /landing/media.
 */
final class SiteMediaService
{
    public const FOLDER = 'site-media';

    public const PUBLISHED_PREFIX = '/landing/media/';

    private const MAX_WIDTH = 1920;

    private const QUALITY = 82;

    /** @return array{path: string, url: string} */
    public function store(UploadedFile $file): array
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['image' => 'Şəkil oxuna bilmədi.']);
        }

        $image = $this->upright($image, $file);
        if (imagesx($image) > self::MAX_WIDTH) {
            $image = imagescale($image, self::MAX_WIDTH);
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        $name = substr(sha1($bytes), 0, 20).'.webp';
        Storage::disk('public')->put(self::FOLDER.'/'.$name, $bytes, 'public');

        return $this->present($name);
    }

    /** Uploaded images first, newest first; then the ones the website came with. */
    public function all(): array
    {
        $disk = Storage::disk('public');
        $uploaded = collect($disk->files(self::FOLDER))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file))
            ->map(fn (string $file) => $this->present(basename($file)))
            ->values()
            ->all();

        $base = rtrim((string) config('app.url'), '/');
        $original = array_map(
            fn (string $file) => ['path' => '/landing/'.basename($file), 'url' => $base.'/landing/'.basename($file)],
            glob(base_path('site/landing/img-*.webp')) ?: [],
        );

        return [...$uploaded, ...$original];
    }

    /** @return array{path: string, url: string} */
    private function present(string $name): array
    {
        return [
            'path' => self::PUBLISHED_PREFIX.$name,
            'url' => rtrim((string) config('app.url'), '/').'/storage/'.self::FOLDER.'/'.$name,
        ];
    }

    /** Phones store sideways pictures with a note saying how to turn them. */
    private function upright(GdImage $image, UploadedFile $file): GdImage
    {
        $exif = $file->getMimeType() === 'image/jpeg' ? @exif_read_data($file->getRealPath()) : false;
        $degrees = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $degrees === 0 ? $image : imagerotate($image, $degrees, 0);
    }
}
