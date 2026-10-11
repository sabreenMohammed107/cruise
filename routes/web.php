<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CruiseController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
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

Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:10,1')->name('newsletter.store');

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
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::middleware('auth:web')->prefix('account')->name('account')->group(function () {
    Route::get('/', [AccountController::class, 'show']);
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('.profile');
    Route::put('/notifications', [AccountController::class, 'updateNotifications'])->name('.notifications');
    Route::put('/password', [AccountController::class, 'updatePassword'])->name('.password');
    Route::post('/favorites/{cruise:slug}', [AccountController::class, 'addFavorite'])->name('.favorites.store');
    Route::delete('/favorites/{cruise:slug}', [AccountController::class, 'removeFavorite'])->name('.favorites.destroy');
});
