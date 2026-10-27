<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Cruise;
use App\Support\ScDataPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public function updateProfile(UpdateProfileRequest $request, ScDataPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        $user->update($request->toSave());

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

        // A new remember token signs out "keep me signed in" on other devices; a new session id here
        // (migrate keeps an admin-panel sign-in in the same session, as in LoginController)
        $request->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $request->session()->migrate(true);

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
