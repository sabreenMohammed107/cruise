<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel: every DELETE request needs the "delete-records" gate (admins only), so supervisors can't
 * delete anything and a new delete route is covered without remembering to add a check.
 */
class AuthorizeDeletes
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('DELETE')) {
            Gate::authorize('delete-records');
        }

        return $next($request);
    }
}
