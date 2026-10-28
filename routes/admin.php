<?php

use App\Http\Controllers\Admin\AgentRequestController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\CruiseArchiveController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SailingController;
use App\Http\Controllers\Admin\SiteSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel — /admin (registered in bootstrap/app.php)
|--------------------------------------------------------------------------
| Staff (admins, supervisors and sellers) can open the dashboard and bookings; a seller only sees and works on
| the bookings assigned to them (checked in Admin\BookingController). Everything else is for admins and
| supervisors, except that supervisors can't delete anything (Middleware\AuthorizeDeletes, every DELETE here)
| or add and edit people (Customers, Admins & staff: gate "manage-users").
*/

Route::get('/', DashboardController::class)->name('dashboard');

// Bookings: created on the website; the team records offline payments here
Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('bookings/{booking:reference}', [BookingController::class, 'show'])->name('bookings.show');
Route::patch('bookings/{booking:reference}', [BookingController::class, 'update'])->name('bookings.update');
Route::post('bookings/{booking:reference}/payments', [BookingController::class, 'storePayment'])->name('bookings.payments.store');

Route::middleware('can:manage-content')->group(function () {
    // Admins and supervisors: cancel, assign bookings to sellers, and remove payment entries (a delete: admins only)
    Route::post('bookings/{booking:reference}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::delete('bookings/{booking:reference}/payments/{payment}', [BookingController::class, 'destroyPayment'])->name('bookings.payments.destroy');
    Route::patch('bookings/{booking:reference}/seller', [BookingController::class, 'assignSeller'])->name('bookings.seller')->middleware('can:assign-bookings');

    // Customers: details page and spreadsheet export (list / edit are the generic screens below)
    Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show')->whereNumber('customer');

    // Sailings already back (return date before today), a year at a time; the cruises list keeps the rest
    Route::get('cruises/archive', CruiseArchiveController::class)->name('cruises.archive');

    // Sailings of a cruise, with their tiered price grid (single / double / 3rd guest per cabin)
    Route::get('cruises/{cruise}/sailings', [SailingController::class, 'index'])->name('cruises.sailings.index');
    Route::get('cruises/{cruise}/sailings/create', [SailingController::class, 'create'])->name('cruises.sailings.create');
    Route::post('cruises/{cruise}/sailings', [SailingController::class, 'store'])->name('cruises.sailings.store');
    Route::get('sailings/{sailing}/edit', [SailingController::class, 'edit'])->name('sailings.edit');
    Route::put('sailings/{sailing}', [SailingController::class, 'update'])->name('sailings.update');
    Route::delete('sailings/{sailing}', [SailingController::class, 'destroy'])->name('sailings.destroy');

    // Homepage content: hero and the sections below it (config/home.php)
    Route::get('home-sections', [HomeSectionController::class, 'index'])->name('home-sections.index');
    Route::get('home-sections/{key}/edit', [HomeSectionController::class, 'edit'])->name('home-sections.edit')->where('key', '[a-z-]+');
    Route::put('home-sections/{key}', [HomeSectionController::class, 'update'])->name('home-sections.update')->where('key', '[a-z-]+');

    // Cruise pages: the same for the ship page, cruises list, cruise details and destinations (config/pages.php)
    Route::get('pages', [HomeSectionController::class, 'pages'])->name('pages.index');
    Route::get('pages/{page}/{key}/edit', [HomeSectionController::class, 'editPage'])->name('pages.edit')->where(['page' => '[a-z]+', 'key' => '[a-z_-]+']);
    Route::put('pages/{page}/{key}', [HomeSectionController::class, 'updatePage'])->name('pages.update')->where(['page' => '[a-z]+', 'key' => '[a-z_-]+']);

    // Footer & contact: description, phones, email, address, WhatsApp and social links (config/site.php)
    Route::get('footer', [SiteSettingController::class, 'edit'])->name('site-settings.edit');
    Route::put('footer', [SiteSettingController::class, 'update'])->name('site-settings.update');

    // Messages from the website's "Contact us" page (opening one marks it as read)
    Route::get('contact-messages', [ContactController::class, 'index'])->name('contact-messages.index');
    Route::get('contact-messages/{contactMessage}', [ContactController::class, 'show'])->name('contact-messages.show')->whereNumber('contactMessage');
    Route::delete('contact-messages/{contactMessage}', [ContactController::class, 'destroy'])->name('contact-messages.destroy')->whereNumber('contactMessage');

    // Applications from the website's "Become an agent" page (its texts: pages.edit with page "agents")
    Route::get('agent-requests', [AgentRequestController::class, 'index'])->name('agent-requests.index');
    Route::get('agent-requests/{agentRequest}', [AgentRequestController::class, 'show'])->name('agent-requests.show')->whereNumber('agentRequest');
    Route::patch('agent-requests/{agentRequest}', [AgentRequestController::class, 'update'])->name('agent-requests.update')->whereNumber('agentRequest');

    // Media library behind every photo field: images on the server + photo URLs in use (JSON pages)
    Route::get('media-library', MediaLibraryController::class)->name('media-library');

    // Generic CRUD screens (config/admin.php). A resource with its own $ability (people) is read-only without it.
    foreach (collect(config('admin.resources'))->flatten() as $resource) {
        $write = $resource::$ability ? ['can:'.$resource::$ability] : [];

        Route::prefix($resource::$slug)->name($resource::$slug.'.')->group(function () use ($resource, $write) {
            Route::get('/', [ResourceController::class, 'index'])->name('index')->defaults('resource', $resource);
            Route::get('create', [ResourceController::class, 'create'])->name('create')->defaults('resource', $resource)->middleware($write);
            Route::post('/', [ResourceController::class, 'store'])->name('store')->defaults('resource', $resource)->middleware($write);
            Route::get('{id}/edit', [ResourceController::class, 'edit'])->name('edit')->defaults('resource', $resource)->whereNumber('id')->middleware($write);
            Route::put('{id}', [ResourceController::class, 'update'])->name('update')->defaults('resource', $resource)->whereNumber('id')->middleware($write);
            Route::delete('{id}', [ResourceController::class, 'destroy'])->name('destroy')->defaults('resource', $resource)->whereNumber('id')->middleware($write);
        });
    }

});
