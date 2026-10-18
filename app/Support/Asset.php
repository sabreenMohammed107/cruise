<?php

namespace App\Support;

/**
 * URLs of the site's own scripts and stylesheets with a version, e.g. /assets/js/core.js?v=1760788875.
 * The version is the file's modification time, so a deploy changes the URL and browsers fetch the new
 * file instead of running a cached copy (IIS sends no Cache-Control header for static files).
 * Blade: @asset('assets/js/core.js').
 */
class Asset
{
    public static function url(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
