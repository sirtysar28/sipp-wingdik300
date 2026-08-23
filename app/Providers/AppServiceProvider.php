<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // Gunakan custom pagination view (compact, match design SIPP)
        Paginator::defaultView('vendor.pagination.sipp');
        Paginator::defaultSimpleView('vendor.pagination.sipp');
    }
}
