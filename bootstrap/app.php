<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Middleware\AuthorizeDeletes;
use App\Http\Middleware\ForgetStaleSessionCookie;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Admin panel: its own sign-in on the "admin" guard, independent of the website's "web" guard
            Route::middleware('web')->prefix('admin')->name('admin.')->group(function () {
                Route::middleware('guest:admin')->group(function () {
                    Route::get('login', [AdminAuthController::class, 'create'])->name('login');
                    Route::post('login', [AdminAuthController::class, 'store'])->middleware('throttle:5,1');
                });
                Route::post('logout', [AdminAuthController::class, 'destroy'])->middleware('auth:admin')->name('logout');

                Route::middleware(['auth:admin', 'can:admin-panel', AuthorizeDeletes::class])->group(base_path('routes/admin.php'));
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $isAdmin = fn (Request $request) => $request->is('admin', 'admin/*');

        // Website language: the sc_lang cookie is written by core.js, so it is not encrypted
        $middleware->encryptCookies(except: [SetLocale::COOKIE]);
        $middleware->web(append: [SetLocale::class]);
        // An old host-only session cookie next to the domain one breaks every form post (419): cleared here
        $middleware->web(prepend: [ForgetStaleSessionCookie::class]);

        // Not signed in: admin pages → admin sign-in, site pages → site sign-in
        $middleware->redirectGuestsTo(fn (Request $request) => $isAdmin($request) ? route('admin.login') : route('login'));
        // Already signed in on that side: skip the sign-in page
        $middleware->redirectUsersTo(fn (Request $request) => $isAdmin($request) ? route('admin.dashboard') : route('account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A form sent with an expired (or unreadable) session: back to the form, with what was typed (not passwords)
        // and a message, instead of a bare "419 Page expired". JSON requests keep their 419.
        // (Laravel has turned the TokenMismatchException into a 419 HttpException by the time this runs.)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }
            $back = $request->is('admin', 'admin/*') ? url()->previous(route('admin.login')) : url()->previous(route('home'));

            return redirect()->to($back)
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                ->with('status', __('site.auth.session_expired'));
        });

        // Receipts together bigger than PHP's post_max_size: PHP drops the whole form. Back to the booking with a
        // message instead of a bare "413" page (the page's script normally stops this before sending).
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            // Thrown by global middleware, before routing and before the session starts: match the path, and pass the
            // message in the address (a flashed error would be lost). The booking page shows it (admin/bookings/show).
            if ($request->isMethod('post') && $request->is('admin/bookings/*/payments')) {
                return redirect()->route('admin.bookings.show', ['booking' => $request->segment(3), 'receipts' => 'too-large']);
            }
        });
    })->create();
