<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use V2 component service provider (with V1 fallback)
        $this->app->register(ComponentServiceProviderV2::class);
        
        // Keep the old one available but not registered
        // $this->app->register(ComponentServiceProvider::class);
    }
}
