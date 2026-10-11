<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmBookingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'regex:'.ConfirmBookingRequest::NAME_PATTERN],
            'last_name' => ['required', 'string', 'regex:'.ConfirmBookingRequest::NAME_PATTERN],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', 'unique:users,email'],
            // Same rule as the template's password form: at least 8 characters, including a number
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ], [
            'first_name.*' => 'Enter your first name using letters only.',
            'last_name.*' => 'Enter your last name using letters only.',
            'email.unique' => 'An account with this email already exists. Sign in instead.',
        ]);

        // role defaults to "customer" in the database and is not mass-assignable
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'notification_prefs' => ['offers' => false, 'whatsapp' => true, 'reminders' => true],
        ]);

        Auth::guard('web')->login($user);
        // Keep an admin-panel sign-in that may live in the same session
        $request->session()->migrate(true);

        return redirect()->intended(route('account'));
    }
}
