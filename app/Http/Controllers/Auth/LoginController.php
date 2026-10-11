<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'That email and password don\'t match an account.',
            ]);
        }

        // New session id, but keep an admin-panel sign-in (guard "admin") that lives in the same session
        $request->session()->migrate(true);

        return redirect()->to($this->intended($request));
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        Auth::guard('web')->logout();
        // Not session()->invalidate(): that would also sign the admin out of /admin
        $request->session()->regenerateToken();

        // dashboard.js signs out with a JSON request
        return $request->expectsJson()
            ? response()->json(['signedOut' => true])
            : redirect()->route('home');
    }

    /**
     * Website sign-in is for the customer side only (admins use /admin/login), so an
     * /admin page left in url.intended is ignored.
     */
    private function intended(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        $path = $intended ? (parse_url($intended, PHP_URL_PATH) ?? '') : '';

        return $intended && ! str_starts_with($path, '/admin') ? $intended : route('account');
    }
}
