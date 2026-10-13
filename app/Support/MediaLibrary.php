<?php

namespace App\Support;

use App\Models\CabinCategory;
use App\Models\Cruise;
use App\Models\Destination;
use App\Models\Ship;
use App\Models\ShipZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Images of ships, zones, destinations, cruises and cabins, and the admin media library they are picked from
 * (admin.partials.media-picker).
 *
 * A record stores the image itself: a path under public/ (e.g. "uploads/media/deck-1a2b3c.jpg") or a
 * photo URL (the template's stock photos). The library lists every image under public/uploads and the
 * official photos in public/assets/img/aroya, plus the photo URLs records use, so a stock photo removed
 * from a gallery can be picked again.
 */
class MediaLibrary
{
    /** New uploads; under public/ so no storage symlink is needed on Windows/XAMPP. */
    public const UPLOAD_DIR = 'uploads/media';

    /** Folders under public/ whose images are listed. */
    public const FOLDERS = ['uploads', 'assets/img/aroya'];

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Image value (path under public/ or URL) → URL for the page, or null when there is no such image. */
    public static function url(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (static::isUrl($value)) {
            return $value;
        }

        return is_file(public_path($value)) ? asset($value) : null;
    }

    /** @param  list<string>|null  $values */
    public static function urls(?array $values): array
    {
        return array_values(array_filter(array_map(static::url(...), $values ?? [])));
    }

    /** Save an uploaded photo in the library and return its value (path under public/). */
    public static function store(UploadedFile $file, string $name): string
    {
        $fileName = (Str::limit(Str::slug($name), 40, '') ?: 'photo').'-'.Str::lower(Str::random(6)).'.'.$file->extension();
        $file->move(public_path(self::UPLOAD_DIR), $fileName);

        return self::UPLOAD_DIR.'/'.$fileName;
    }

    /**
     * Every library image, newest first, optionally filtered by name.
     *
     * @return list<array{value: string, url: string, name: string}>
     */
    public static function items(string $term = ''): array
    {
        $items = static::files()->concat(static::photoUrls());

        $term = mb_strtolower(trim($term));
        if ($term !== '') {
            $items = $items->filter(fn ($item) => str_contains(mb_strtolower($item['name']), $term));
        }

        return $items->values()->all();
    }

    /** Images in the library folders, newest first. */
    private static function files(): Collection
    {
        $files = [];
        foreach (self::FOLDERS as $folder) {
            $root = public_path($folder);
            if (! is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $path = $folder.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if ($file->isFile() && static::isLibraryPath($path)) {
                    $files[] = ['path' => $path, 'mtime' => $file->getMTime()];
                }
            }
        }
        usort($files, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return collect($files)->map(fn ($f) => ['value' => $f['path'], 'url' => asset($f['path']), 'name' => basename($f['path'])]);
    }

    /** Photo URLs (not files on this server) that records use, e.g. the template's stock photos. */
    private static function photoUrls(): Collection
    {
        return collect(static::usedValues())
            ->filter(fn ($value) => static::isUrl($value))
            ->unique()
            ->values()
            ->map(fn ($url) => ['value' => $url, 'url' => $url, 'name' => 'Photo from '.parse_url($url, PHP_URL_HOST)]);
    }

    /** @return list<string> every image value stored on a record */
    public static function usedValues(): array
    {
        return collect()
            ->merge(Ship::pluck('image'))->merge(Ship::pluck('hero_image'))
            ->merge(ShipZone::pluck('image'))
            ->merge(Destination::pluck('image'))
            ->merge(Cruise::withTrashed()->pluck('image'))->merge(Cruise::withTrashed()->pluck('gallery')->flatten())
            ->merge(CabinCategory::pluck('images')->flatten())
            ->filter()
            ->values()
            ->all();
    }

    /** An image file under one of the library folders (no "../", image extension only). */
    public static function isLibraryPath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return ! str_contains($path, '..')
            && Str::startsWith($path, array_map(fn ($folder) => $folder.'/', self::FOLDERS))
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)
            && is_file(public_path($path));
    }

    public static function isUrl(string $value): bool
    {
        return (bool) preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /** A value a photo field may store: a library file or a photo URL. */
    public static function isValid(mixed $value): bool
    {
        return is_string($value) && $value !== '' && (static::isUrl($value) || static::isLibraryPath($value));
    }

    /** Largest photo upload accepted: 5 MB, or less when PHP's upload_max_filesize / post_max_size are lower. */
    public static function maxUploadBytes(): int
    {
        $bytes = function (string $setting): int {
            $value = trim((string) ini_get($setting));
            $number = (int) $value;

            return match (strtolower(substr($value, -1))) {
                'g' => $number * 1024 ** 3,
                'm' => $number * 1024 ** 2,
                'k' => $number * 1024,
                default => $number,
            } ?: PHP_INT_MAX; // 0 = no limit
        };

        return min(5 * 1024 ** 2, $bytes('upload_max_filesize'), $bytes('post_max_size'));
    }

    /** maxUploadBytes() for people, e.g. "5 MB" or "1.5 MB". */
    public static function maxUploadLabel(): string
    {
        return rtrim(rtrim(number_format(static::maxUploadBytes() / 1024 ** 2, 1), '0'), '.').' MB';
    }
}
