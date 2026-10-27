<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * "Forgot password?" for website customers, on Laravel's password broker (config/auth.php → passwords.users:
 * tokens expire after 60 minutes, one new link per minute). The email is queued (App\Notifications\ResetPassword).
 */
class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        // Staff (admins, sellers) sign in at /admin/login, so like LoginController they are treated as unknown here
        Password::sendResetLink($request->only('email'), function (User $user, string $token) {
            if (! $user->isStaff()) {
                $user->sendPasswordResetNotification($token);
            }
        });

        // Same answer whether or not the address has an account (or was throttled), so emails can't be guessed.
        // The page then shows "check your inbox" for this address instead of the form.
        return back()->with('reset_sent', $request->input('email'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            // Same rule as registration: at least 8 characters, including a number
            'password' => ['required', 'confirmed', PasswordRule::min(8)->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // "hashed" cast on User; a new remember token signs out "keep me signed in" sessions elsewhere
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('site.auth.reset_link_invalid')]);
        }

        return redirect()->route('login')->with('status', __('site.auth.password_reset_done'));
    }
}
