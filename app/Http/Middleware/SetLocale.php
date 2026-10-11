<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Website language from the sc_lang cookie ("ar" or "en"), set by the header's عربي button (core.js).
 * The admin panel always stays in English.
 */
class SetLocale
{
    public const COOKIE = 'sc_lang';

    public function handle(Request $request, Closure $next): Response
    {
        $arabic = ! $request->is('admin', 'admin/*') && $request->cookie(self::COOKIE) === 'ar';
        app()->setLocale($arabic ? 'ar' : 'en');

        return $next($request);
    }
}
