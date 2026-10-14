<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\User;
use App\Support\ScDataPresenter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Signing in to /admin: admins and sellers (routes use can:admin-panel).
        Gate::define('admin-panel', fn (User $user) => $user->isStaff());
        // Everything in the panel except the dashboard and a seller's own bookings (can:admin, routes/admin.php).
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        // A booking's admin page and voucher: admins, and the seller it is assigned to.
        Gate::define('view-booking', fn (User $user, Booking $booking) => $user->isAdmin()
            || ($user->isSeller() && $booking->seller_id === $user->id));

        // Bookings screens: the sellers an admin can assign a booking to (sellers themselves get none)
        View::composer(['admin.bookings.index', 'admin.bookings.table', 'admin.bookings.show'], function ($view) {
            $view->with('sellers', auth('admin')->user()?->isAdmin()
                ? User::sellers()->orderBy('name')->get(['id', 'name'])
                : collect());
        });

        // Every public page needs window.SC_DATA for core.js; pages that don't pass one get the minimal set.
        View::composer('layouts.app', function ($view) {
            if (! array_key_exists('scData', $view->getData())) {
                $view->with('scData', app(ScDataPresenter::class)->minimal(auth('web')->user()));
            }
        });

        // @bi('site.search.guests') → the fixed word in English and Arabic (App\Support\Bi, lang/{en,ar})
        Blade::directive('bi', fn (string $expression) => "<?php echo \\App\\Support\\Bi::trans({$expression}); ?>");

        Paginator::useTailwind();
    }
}
