<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Website language from the sc_lang cookie ("ar" or "en"), set by the header's عربي button (core.js); without the
 * cookie, config('cruise.default_locale') (Arabic). The admin panel always stays in English.
 */
class SetLocale
{
    public const COOKIE = 'sc_lang';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*')) {
            app()->setLocale('en');

            return $next($request);
        }

        // The visitor's choice; before they make one, the site default (config/cruise.php default_locale)
        $lang = $request->cookie(self::COOKIE);
        if (! in_array($lang, ['ar', 'en'], true)) {
            $lang = config('cruise.default_locale') === 'en' ? 'en' : 'ar';
        }
        app()->setLocale($lang);

        return $next($request);
    }
}
