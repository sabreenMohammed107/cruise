<?php

namespace App\Support;

use Throwable;

/**
 * Smaller WebP copies of an uploaded photo, so phones don't download the full desktop image.
 *
 * generate() writes "{name}-{width}.webp" next to the original (e.g. uploads/home/home-hero-ab12cd-768.webp)
 * for every width in WIDTHS that is smaller than the original, plus a full-size WebP when the original is a
 * JPG/PNG (a 2 MB PNG becomes a few hundred KB); srcset() lists the copies that exist, for <img srcset> /
 * <source srcset>, with the original as the largest candidate only when there is no full-size copy. Plain GD only (no Composer package: live has no shell).
 * If GD can't read the file, there are simply no copies and the original is served as before.
 */
class ResponsiveImage
{
    public const WIDTHS = [480, 768, 1080, 1600, 2400];

    private const QUALITY = 80;

    /** Larger photos are skipped: GD needs ~5 bytes per pixel in memory. */
    private const MAX_PIXELS = 40_000_000;

    /** Make the smaller copies of a photo (path under public/). Replaces copies that already exist. */
    public static function generate(?string $path): void
    {
        $file = static::file($path);
        if ($file === null || ! function_exists('imagewebp')) {
            return;
        }

        try {
            $info = @getimagesize($file);
            if (! $info || $info[0] * $info[1] > self::MAX_PIXELS) {
                return;
            }
            @ini_set('memory_limit', '512M');

            $source = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
                IMAGETYPE_PNG => @imagecreatefrompng($file),
                IMAGETYPE_WEBP => @imagecreatefromwebp($file),
                default => false,
            };
            if (! $source) {
                return;
            }
            if ($info[2] === IMAGETYPE_JPEG) {
                $source = static::orient($source, $file);
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $widths = array_filter(self::WIDTHS, fn (int $w) => $w < $width);
            if ($info[2] !== IMAGETYPE_WEBP) {
                $widths[] = $width;
            }
            foreach ($widths as $w) {
                $h = (int) round($height * $w / $width);
                $copy = imagecreatetruecolor($w, $h);
                imagealphablending($copy, false);
                imagesavealpha($copy, true);
                imagecopyresampled($copy, $source, 0, 0, 0, 0, $w, $h, $width, $height);
                imagewebp($copy, static::variant($file, $w), self::QUALITY);
                imagedestroy($copy);
            }
            imagedestroy($source);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Whether the photo should have copies but has none yet (uploaded before this existed). */
    public static function missing(?string $path): bool
    {
        $file = static::file($path);
        if ($file === null) {
            return false;
        }
        $size = @getimagesize($file);

        return $size && $size[0] > self::WIDTHS[0] && ! is_file(static::variant($file, self::WIDTHS[0]));
    }

    /** "url-480 480w, url-768 768w, …, original 2000w", or null when the photo isn't an upload on disk. */
    public static function srcset(?string $path): ?string
    {
        $file = static::file($path);
        $size = $file ? @getimagesize($file) : false;
        if (! $size) {
            return null;
        }

        $candidates = [];
        foreach (self::WIDTHS as $w) {
            if ($w < $size[0] && is_file(static::variant($file, $w))) {
                $candidates[] = asset(static::variant($path, $w)).' '.$w.'w';
            }
        }
        $full = is_file(static::variant($file, $size[0])) ? static::variant($path, $size[0]) : $path;
        $candidates[] = asset($full).' '.$size[0].'w';

        return implode(', ', $candidates);
    }

    /** Height ÷ width of the photo (1.7778 for 1080×1920), or null. */
    public static function heightRatio(?string $path): ?float
    {
        $file = static::file($path);
        $size = $file ? @getimagesize($file) : false;

        return $size && $size[0] > 0 ? round($size[1] / $size[0], 4) : null;
    }

    /** Delete the copies of a photo (the original is deleted by the caller). */
    public static function delete(?string $path): void
    {
        $file = static::file($path);
        if ($file === null) {
            return;
        }
        $size = @getimagesize($file);
        foreach ([...self::WIDTHS, ...($size ? [$size[0]] : [])] as $w) {
            if (is_file($variant = static::variant($file, $w))) {
                @unlink($variant);
            }
        }
    }

    /** Absolute path of an existing file under public/, or null. */
    private static function file(?string $path): ?string
    {
        if (! $path || str_contains($path, '..')) {
            return null;
        }
        $file = public_path($path);

        return is_file($file) ? $file : null;
    }

    /** "dir/name.jpg" → "dir/name-768.webp" (works for public paths and absolute paths). */
    private static function variant(string $path, int $width): string
    {
        $info = pathinfo($path);

        return ($info['dirname'] === '.' ? '' : $info['dirname'].'/').$info['filename'].'-'.$width.'.webp';
    }

    /** Phone photos are often stored sideways with an EXIF "rotate" flag; apply it so the copies stand upright. */
    private static function orient(\GdImage $image, string $file): \GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($file) : false;
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
