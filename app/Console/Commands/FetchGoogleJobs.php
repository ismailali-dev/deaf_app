<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleJobsService;

class FetchGoogleJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:google-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch jobs from Google API and store them in the database';

    /**
     * Execute the console command.
     *
     * @param GoogleJobsService $googleJobsService
     * @return void
     */
    public function handle(GoogleJobsService $googleJobsService)
    {
        $googleJobsService->fetchAndStoreJobs();
        $this->info('Google jobs fetched and stored successfully.');
    }
}
