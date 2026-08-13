<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Railway (dan hosting sejenis) men-terminate HTTPS di proxy/edge,
        // lalu meneruskan request ke container sebagai HTTP biasa. Tanpa ini,
        // Laravel generate URL asset (css/js) dengan http://, yang diblokir
        // browser sebagai "Mixed Content" saat halaman diakses via https://.
        if (! $this->app->environment('local')) {
            URL::forceScheme('https');
        }
    }
}