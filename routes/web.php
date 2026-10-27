<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CruiseController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreBoardingGuideController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site (legacy-html pages)
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/ship', [PageController::class, 'ship'])->name('ship');
Route::get('/destinations', [PageController::class, 'destinations'])->name('destinations.index');
Route::get('/cruises', [PageController::class, 'catalog'])->name('cruises.index');
Route::get('/cruises/{cruise:slug}', [CruiseController::class, 'show'])->name('cruises.show');

// Visas, check-in, prohibited items, kids policy: Admin → Cruise pages → Pre-boarding guide
Route::get('/pre-boarding-guide', PreBoardingGuideController::class)->name('guide');

Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:10,1')->name('newsletter.store');

// Contact us: messages land in Admin → Contact messages
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');

// Become an agent: texts from Admin → Cruise pages, applications in Admin → Agents
Route::get('/agents', [AgentController::class, 'show'])->name('agents');
Route::post('/agents', [AgentController::class, 'store'])->middleware('throttle:5,1')->name('agents.store');

// Booking: guests → review → confirm (no online payment)
Route::get('/booking', [BookingController::class, 'show'])->name('booking.show');
Route::post('/booking/promo', [BookingController::class, 'promo'])->middleware('throttle:20,1')->name('booking.promo');
Route::post('/booking/confirm', [BookingController::class, 'confirm'])->middleware('throttle:10,1')->name('booking.confirm');
Route::get('/booking/{booking:reference}/confirmation', [BookingController::class, 'confirmation'])->name('booking.confirmation');

/*
|--------------------------------------------------------------------------
| Sign in / account (dashboard.html)
|--------------------------------------------------------------------------
*/
// The website's sign-in uses the "web" guard explicitly; the admin panel has its own ("admin", bootstrap/app.php)
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');

    // Forgot password: emailed link (PasswordResetController)
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::middleware('auth:web')->prefix('account')->name('account')->group(function () {
    Route::get('/', [AccountController::class, 'show']);
    Route::put('/profile', [AccountController::class, 'updateProfile'])->middleware('throttle:20,1')->name('.profile');
    Route::put('/notifications', [AccountController::class, 'updateNotifications'])->middleware('throttle:30,1')->name('.notifications');
    // Low limit: the form checks the current password, so it must not allow guessing it
    Route::put('/password', [AccountController::class, 'updatePassword'])->middleware('throttle:5,1')->name('.password');
    Route::post('/favorites/{cruise:slug}', [AccountController::class, 'addFavorite'])->name('.favorites.store');
    Route::delete('/favorites/{cruise:slug}', [AccountController::class, 'removeFavorite'])->name('.favorites.destroy');
});
