<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmBookingRequest;
use App\Models\Cruise;
use App\Support\ScDataPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The guest's account (dashboard.html). dashboard.js renders it from SC_DATA.account and
 * saves changes through the JSON endpoints below.
 */
class AccountController extends Controller
{
    public function show(Request $request, ScDataPresenter $presenter): View
    {
        $user = $request->user();

        return view('account.dashboard', [
            'user' => $user,
            'scData' => $presenter->make($user, account: $presenter->account($user)),
        ]);
    }

    public function updateProfile(Request $request, ScDataPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first' => ['required', 'string', 'regex:'.ConfirmBookingRequest::NAME_PATTERN],
            'last' => ['required', 'string', 'regex:'.ConfirmBookingRequest::NAME_PATTERN],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'dial' => ['nullable', Rule::in(array_column(config('cruise.dial_codes'), 0))],
            'phone' => ['nullable', 'regex:/^\d{7,12}$/'],
            'dob' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'nationality' => ['nullable', Rule::in(config('cruise.nationalities'))],
        ], [
            'first.*' => __('site.account.first_required'),
            'last.*' => __('site.account.last_required'),
            'email.unique' => __('site.account.email_taken'),
            'email.*' => __('site.booking.errors.email_invalid'),
            'phone.*' => __('site.booking.errors.phone_length'),
        ]);

        $user->update([
            'first_name' => $data['first'],
            'last_name' => $data['last'],
            'email' => strtolower($data['email']),
            'phone_dial' => $data['dial'] ?? null,
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['dob'] ?? null,
            'nationality' => $data['nationality'] ?? null,
        ]);

        return response()->json(['profile' => $presenter->account($user->fresh())['profile']]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offers' => ['required', 'boolean'],
            'whatsapp' => ['required', 'boolean'],
            'reminders' => ['required', 'boolean'],
        ]);

        $request->user()->update(['notification_prefs' => $data]);

        return response()->json(['notif' => $data]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ], [
            'current_password.current_password' => __('site.account.current_password_wrong'),
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['updated' => true]);
    }

    public function addFavorite(Request $request, Cruise $cruise): JsonResponse
    {
        $request->user()->favorites()->syncWithoutDetaching([$cruise->id]);

        return response()->json(['favorites' => $request->user()->favorites()->pluck('slug')]);
    }

    public function removeFavorite(Request $request, Cruise $cruise): JsonResponse
    {
        $request->user()->favorites()->detach($cruise->id);

        return response()->json(['favorites' => $request->user()->favorites()->pluck('slug')]);
    }
}
