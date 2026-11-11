<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears a leftover session cookie for this host only, when the session cookie is set for a whole domain
 * (SESSION_DOMAIN, ".safer.travel" on the live server).
 *
 * A browser that visited the site before the session cookie moved to the domain keeps the old one for
 * "cruises.safer.travel" and sends both, the old one first. PHP reads the first, so every request opens a
 * session that never holds the form's token: every form post ends in "419 Page expired" (seen with agents who had
 * applied on the site before getting their account). When the cookie comes twice, the answer expires the host-only
 * one, so the next page works.
 *
 * Prepended to the "web" group (bootstrap/app.php), so it also adds to the answer of a refused post.
 */
class ForgetStaleSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $name = config('session.cookie');
        if (config('session.domain') && self::sentTwice($request, $name)) {
            // No domain: the host-only cookie, not the domain one the session uses now
            $response->headers->setCookie(Cookie::create($name, null, 1, config('session.path', '/'), null,
                config('session.secure'), true, false, config('session.same_site')));
        }

        return $response;
    }

    public static function sentTwice(Request $request, string $name): bool
    {
        return preg_match_all('/(?:^|;)\s*'.preg_quote($name, '/').'=/', (string) $request->headers->get('cookie')) > 1;
    }
}
