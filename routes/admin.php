<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\ImageSlotController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SailingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel — /admin, signed-in admins only (registered in bootstrap/app.php)
|--------------------------------------------------------------------------
*/

Route::get('/', DashboardController::class)->name('dashboard');

// Bookings: created on the website; the team records offline payments and cancels here
Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('bookings/{booking:reference}', [BookingController::class, 'show'])->name('bookings.show');
Route::patch('bookings/{booking:reference}', [BookingController::class, 'update'])->name('bookings.update');
Route::post('bookings/{booking:reference}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
Route::post('bookings/{booking:reference}/payments', [BookingController::class, 'storePayment'])->name('bookings.payments.store');
Route::delete('bookings/{booking:reference}/payments/{payment}', [BookingController::class, 'destroyPayment'])->name('bookings.payments.destroy');

// Sailings of a cruise, with their price grid
Route::get('cruises/{cruise}/sailings', [SailingController::class, 'index'])->name('cruises.sailings.index');
Route::get('cruises/{cruise}/sailings/create', [SailingController::class, 'create'])->name('cruises.sailings.create');
Route::post('cruises/{cruise}/sailings', [SailingController::class, 'store'])->name('cruises.sailings.store');
Route::get('sailings/{sailing}/edit', [SailingController::class, 'edit'])->name('sailings.edit');
Route::put('sailings/{sailing}', [SailingController::class, 'update'])->name('sailings.update');
Route::post('sailings/{sailing}/recalculate', [SailingController::class, 'recalculate'])->name('sailings.recalculate');
Route::delete('sailings/{sailing}', [SailingController::class, 'destroy'])->name('sailings.destroy');

// Homepage content: hero and the sections below it (config/home.php)
Route::get('home-sections', [HomeSectionController::class, 'index'])->name('home-sections.index');
Route::get('home-sections/{key}/edit', [HomeSectionController::class, 'edit'])->name('home-sections.edit')->where('key', '[a-z-]+');
Route::put('home-sections/{key}', [HomeSectionController::class, 'update'])->name('home-sections.update')->where('key', '[a-z-]+');

// Cruise pages: the same for the ship page, cruises list, cruise details and destinations (config/pages.php)
Route::get('pages', [HomeSectionController::class, 'pages'])->name('pages.index');
Route::get('pages/{page}/{key}/edit', [HomeSectionController::class, 'editPage'])->name('pages.edit')->where(['page' => '[a-z]+', 'key' => '[a-z_-]+']);
Route::put('pages/{page}/{key}', [HomeSectionController::class, 'updatePage'])->name('pages.update')->where(['page' => '[a-z]+', 'key' => '[a-z_-]+']);

// Photo slots (photos.html)
Route::get('image-slots', [ImageSlotController::class, 'index'])->name('image-slots.index');
Route::post('image-slots', [ImageSlotController::class, 'store'])->name('image-slots.store');
Route::delete('image-slots/{slot:key}/slot', [ImageSlotController::class, 'remove'])->name('image-slots.remove');
Route::post('image-slots/{slot:key}', [ImageSlotController::class, 'update'])->name('image-slots.update');
Route::delete('image-slots/{slot:key}', [ImageSlotController::class, 'destroy'])->name('image-slots.destroy');

// Media library behind every photo field: photo slots + images in public/uploads (JSON pages)
Route::get('media-library', MediaLibraryController::class)->name('media-library');

// Generic CRUD screens (config/admin.php)
foreach (collect(config('admin.resources'))->flatten() as $resource) {
    Route::prefix($resource::$slug)->name($resource::$slug.'.')->group(function () use ($resource) {
        Route::get('/', [ResourceController::class, 'index'])->name('index')->defaults('resource', $resource);
        Route::get('create', [ResourceController::class, 'create'])->name('create')->defaults('resource', $resource);
        Route::post('/', [ResourceController::class, 'store'])->name('store')->defaults('resource', $resource);
        Route::get('{id}/edit', [ResourceController::class, 'edit'])->name('edit')->defaults('resource', $resource)->whereNumber('id');
        Route::put('{id}', [ResourceController::class, 'update'])->name('update')->defaults('resource', $resource)->whereNumber('id');
        Route::delete('{id}', [ResourceController::class, 'destroy'])->name('destroy')->defaults('resource', $resource)->whereNumber('id');
    });
}
