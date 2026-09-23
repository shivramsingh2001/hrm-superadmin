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
        // The panel is Bootstrap 5 throughout — without this, every ->links() call
        // falls back to Laravel's default Tailwind pagination view, which renders
        // unstyled (oversized) SVG arrows since Tailwind's CSS isn't loaded here.
        Paginator::useBootstrapFive();
    }
}
