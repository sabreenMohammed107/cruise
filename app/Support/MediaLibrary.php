<?php

namespace App\Support;

use App\Models\ImageSlot;
use Illuminate\Support\Collection;

/**
 * The admin media library behind every photo field (admin.partials.media-picker).
 *
 * Records keep storing photo slot keys, so the website is unchanged. The library shows two kinds of images:
 *   - every photo slot that has a picture (value = the slot key)
 *   - loose images under public/uploads that aren't a slot's own upload, e.g. homepage photos
 *     (value = "file:{path under public/uploads}"); picking one copies it into a new photo slot on save.
 */
class MediaLibrary
{
    public const FILE_PREFIX = 'file:';

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Every library image, newest first, optionally filtered by name.
     *
     * @return list<array{value: string, url: string, name: string}>
     */
    public static function items(string $term = ''): array
    {
        $slots = ImageSlot::orderByDesc('updated_at')->orderByDesc('id')->get();
        $slotPaths = $slots->pluck('path')->filter()->all();

        $items = $slots
            ->map(fn (ImageSlot $slot) => ['value' => $slot->key, 'url' => $slot->url(), 'name' => $slot->label])
            ->filter(fn ($item) => $item['url'] !== '')
            ->values()
            ->concat(static::looseFiles($slotPaths));

        $term = mb_strtolower(trim($term));
        if ($term !== '') {
            $items = $items->filter(fn ($item) => str_contains(mb_strtolower($item['name'].' '.$item['value']), $term));
        }

        return $items->values()->all();
    }

    /**
     * Images under public/uploads that aren't already a slot's photo (newest first).
     *
     * @param  list<string>  $slotPaths  slot uploads, relative to public/
     */
    private static function looseFiles(array $slotPaths): Collection
    {
        $root = public_path('uploads');
        if (! is_dir($root)) {
            return collect();
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($file->isFile() && static::isLibraryPath($relative) && ! in_array('uploads/'.$relative, $slotPaths, true)) {
                $files[] = ['path' => $relative, 'mtime' => $file->getMTime()];
            }
        }
        usort($files, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return collect($files)->map(fn ($f) => [
            'value' => self::FILE_PREFIX.$f['path'],
            'url' => asset('uploads/'.$f['path']),
            'name' => basename($f['path']),
        ]);
    }

    /** A pickable image path under public/uploads (no "../", image extension only). */
    public static function isLibraryPath(string $path): bool
    {
        return $path !== '' && ! str_contains($path, '..')
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)
            && is_file(public_path('uploads/'.$path));
    }

    /** True for an existing slot key or a pickable "file:" library image. */
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }
        if (str_starts_with($value, self::FILE_PREFIX)) {
            return static::isLibraryPath(substr($value, strlen(self::FILE_PREFIX)));
        }

        return ImageSlot::where('key', $value)->exists();
    }

    /**
     * Picked value → slot key to store. A loose file becomes a new photo slot (a copy, so deleting the
     * slot later never touches the original); a slot key is returned unchanged.
     */
    public static function resolve(?string $value, string $label, string $keyHint): ?string
    {
        if (! $value || ! str_starts_with($value, self::FILE_PREFIX)) {
            return $value ?: null;
        }

        $path = substr($value, strlen(self::FILE_PREFIX));

        return static::isLibraryPath($path) ? ImageSlot::fromFile(public_path('uploads/'.$path), $label, $keyHint)->key : null;
    }

    /** Preview URL for a picked value (slot key or "file:" path), or null. */
    public static function previewUrl(?string $value, Collection $slots): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, self::FILE_PREFIX)) {
            return asset('uploads/'.substr($value, strlen(self::FILE_PREFIX)));
        }

        return $slots->firstWhere('key', $value)?->url() ?: null;
    }
}
