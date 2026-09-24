<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Children's task photos: kept on the private disk (never a public URL —
 * the dozor sees them through an authorised route) and always re-encoded,
 * which drops EXIF/GPS metadata and caps the size.
 */
class AttemptPhotos
{
    public const DISK = 'local';

    public const DIRECTORY = 'attempt-photos';

    public const MAX_EDGE = 1600;

    public const QUALITY = 85;

    /** Sanitises the image file and stores it; returns its disk path. */
    public static function store(string $sourcePath): string
    {
        $path = self::DIRECTORY.'/'.Str::random(40).'.jpg';

        Storage::disk(self::DISK)->put($path, self::sanitize($sourcePath));

        return $path;
    }

    /**
     * Decodes the image, applies its EXIF orientation, shrinks it to
     * MAX_EDGE and re-encodes it as a plain JPEG without any metadata.
     */
    public static function sanitize(string $sourcePath): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($sourcePath));

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be read.']);
        }

        $image = self::orient($image, $sourcePath);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
        }

        ob_start();
        imagejpeg($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /** @param  iterable<string>  $paths */
    public static function delete(iterable $paths): void
    {
        $paths = collect($paths)->filter()->values()->all();

        if ($paths === []) {
            return;
        }

        Storage::disk(self::DISK)->delete($paths);
        // Photos uploaded before they moved to the private disk.
        Storage::disk('public')->delete($paths);
    }

    public static function response(string $path): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function orient(\GdImage $image, string $sourcePath): \GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($sourcePath) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // EXIF orientations 2–8: mirror first where needed, then rotate
        // (imagerotate turns counter-clockwise).
        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif (in_array($orientation, [4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }
}
