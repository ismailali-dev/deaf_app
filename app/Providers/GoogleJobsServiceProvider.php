<?php

namespace App\Providers;

use App\Services\GoogleJobsService;
use Illuminate\Support\ServiceProvider;

class GoogleJobsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(GoogleJobsService $googleJobsService)
    {
        $googleJobsService->fetchAndStoreJobs();
    }
}