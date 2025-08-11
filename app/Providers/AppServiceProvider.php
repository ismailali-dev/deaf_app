<?php

namespace App\Providers;

use App\Channels\FirebaseChannel;
use App\Services\FirebaseService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         $this->app->singleton(\App\Channels\FirebaseChannel::class, function ($app) {
            return new \App\Channels\FirebaseChannel($app->make(\App\Services\FirebaseService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }
        
       
    }
}
