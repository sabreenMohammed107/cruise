<?php

namespace App\Providers;

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
        // Protects everything under /admin (routes use the can:admin middleware).
        Gate::define('admin', fn (User $user) => $user->isAdmin());

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
