<?php

namespace App\Providers;

use App\Services\Cart\Cart;
use App\Support\Access\AccessManager;
use App\Support\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Cart::class);
        $this->app->scoped(CurrentTenant::class);
        $this->app->scoped(AccessManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Views are Bootstrap 4 (carried over from the Laravel 7 app).
        Paginator::useBootstrapFour();

        // Platform staff may do anything inside the platform panel (and only there).
        Gate::before(function ($user) {
            if ($user->is_platform_admin && Filament::getCurrentPanel()?->getId() === 'platform') {
                return true;
            }

            return null;
        });
    }
}
