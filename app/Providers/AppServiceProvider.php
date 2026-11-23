<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\User;
use App\Services\AgentPricing;
use App\Support\ScDataPresenter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use App\Services\Assistant\Assistant;
use Illuminate\Support\Facades\RateLimiter;
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
        // Agent rates load once per request (and per queued job)
        $this->app->scoped(AgentPricing::class);
        // Live offers load once per request, like the agent rates
        $this->app->scoped(\App\Services\OfferService::class);

        // php artisan serve on Windows: the variable is "SystemRoot", but Laravel only passes through "SYSTEMROOT"
        // (case-sensitive), so the server process loses it, can't open a socket and fails with "Failed to listen (reason: ?)".
        if (PHP_OS_FAMILY === 'Windows' && $this->app->runningInConsole()) {
            \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables[] = 'SystemRoot';
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Chat assistant: each message costs a Claude call, so a visitor (account, or IP) gets 60 an hour
        // Website chat: messages a minute and an hour per visitor, set in Admin → Assistant chats
        RateLimiter::for('assistant', function (Request $request) {
            $who = $request->user('web')?->id ?: $request->ip();

            return [
                Limit::perMinute(Assistant::limit('per_minute'))->by('assistant-m:'.$who),
                Limit::perHour(Assistant::limit('per_hour'))->by('assistant-h:'.$who),
            ];
        });

        // The super admin (developer, UserRole::SuperAdmin) passes every check in the panel
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);
        // Signing in to /admin: admins, supervisors and sellers (routes use can:admin-panel).
        Gate::define('admin-panel', fn (User $user) => $user->isStaff());
        // Everything in the panel except the dashboard and a seller's own bookings (can:manage-content, routes/admin.php).
        Gate::define('manage-content', fn (User $user) => $user->isAdmin() || $user->isSupervisor());
        // Deleting anything: admins only (every DELETE in the panel, Middleware\AuthorizeDeletes).
        Gate::define('delete-records', fn (User $user) => $user->isAdmin());
        // Admin → Assistant chats (the website chat's conversations, limits and cost): the super admin only, who
        // passes every check through Gate::before above; nobody else
        Gate::define('manage-assistant', fn (User $user) => false);
        // Admin → Assistant tickets (the chat's package, in tickets): the super admin only for now. To show it to the
        // admins, return $user->isAdmin() here; creating and renewing packages and the USD amounts stay the super admin's.
        Gate::define('manage-assistant-tickets', fn (User $user) => false);
        // Adding and editing people (Customers, Admins & staff): admins only; supervisors can look at the lists.
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());
        // Cancelling a booking: admins and the super admin only; not supervisors, sellers or agents (step 37).
        // Route admin.bookings.cancel (can:cancel-bookings), Admin\BookingController::cancel() and the button.
        Gate::define('cancel-bookings', fn (User $user) => $user->isAdmin() || $user->isSuperAdmin());
        // Choosing who a booking is assigned to (any admin, supervisor or seller): admins and supervisors.
        Gate::define('assign-bookings', fn (User $user) => $user->isAdmin() || $user->isSupervisor());
        // A booking's admin page and voucher: admins, supervisors, and the seller it is assigned to.
        Gate::define('view-booking', fn (User $user, Booking $booking) => $user->can('manage-content')
            || ($user->isSeller() && $booking->seller_id === $user->id));

        // Bookings screens: the staff a booking can be assigned to, admins, supervisors and sellers (User::assignable).
        // Only for people who assign (admins, supervisors); sellers get none and just see the name.
        View::composer(['admin.bookings.index', 'admin.bookings.table', 'admin.bookings.show'], function ($view) {
            $view->with('assignees', auth('admin')->user()?->can('assign-bookings')
                ? User::assignable()->get(['id', 'name', 'role'])
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
        // @asset('assets/js/core.js') → versioned URL, so browsers don't keep running an old script after a deploy
        Blade::directive('asset', fn (string $expression) => "<?php echo e(\\App\\Support\\Asset::url({$expression})); ?>");

        Paginator::useTailwind();
    }
}
