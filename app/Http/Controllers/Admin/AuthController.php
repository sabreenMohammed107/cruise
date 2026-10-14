<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in for the admin panel, on the separate "admin" guard. Signing in or out here
 * doesn't touch the customer signed in on the website (guard "web"), and vice versa.
 */
class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = Auth::guard('admin');
        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => "That email and password don't match an account."]);
        }

        if (! $guard->user()->isStaff()) {
            $guard->logout();
            throw ValidationException::withMessages(['email' => "This account doesn't have access to the admin panel."]);
        }

        // Regenerate the session id, but keep the website's sign-in that lives in the same session
        $request->session()->migrate(true);

        return redirect()->to($this->intended($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        // Not session()->invalidate(): that would also sign the customer out of the website
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /** Back to the admin page that asked for sign-in; never to a website page. */
    private function intended(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        return $intended && str_starts_with(parse_url($intended, PHP_URL_PATH) ?? '', '/admin')
            ? $intended
            : route('admin.dashboard');
    }
}
