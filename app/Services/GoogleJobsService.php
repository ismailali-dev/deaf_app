<?php

namespace App\Services;

use App\Models\Employment;
use Illuminate\Support\Facades\Http;

class GoogleJobsService
{
    /**
     * Fetch jobs from Google Custom Search API and store them in the database.
     *
     * @return void
     */
    public function fetchAndStoreJobs()
{
    // Define Google API credentials
    $apiKey = config('services.google.api_key'); // From .env
    $cx = config('services.google.cx');         // Custom search engine ID
    $query = 'Deaf jobs in USA';                // Search query

    // Get the current date to use for limiting the results
    $currentDate = now()->toDateString();

    try {
        // Make a GET request to Google Custom Search API
        $response = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => $apiKey,
            'cx' => $cx,
            'q' => $query,
        ]);

        if ($response->successful()) {
            $results = $response->json()['items'] ?? [];

            // Limit to only 15 jobs per day
            $existingJobsCount = Employment::whereDate('created_at', $currentDate)->count();
            if ($existingJobsCount >= 15) {
                // If 15 jobs already exist for today, don't store more
                logger()->info('Already fetched 15 jobs for today.');
                return;
            }

            // Store jobs or update existing ones
            foreach ($results as $result) {
                // Skip if we've already fetched 15 jobs for today
                if (Employment::whereDate('created_at', $currentDate)->count() >= 15) {
                    break;
                }

                Employment::updateOrCreate(
                    ['application_link' => $result['link']], // Unique field to avoid duplicates
                    [
                        'title' => $result['title'],
                        'short_description' => $result['snippet'],
                        'long_description' => $result['snippet'],
                        'company_name' => $result['displayLink'],
                        'address' => null,
                        'offer_salary' => null,
                        'status' => 1,
                        'employe_type' => 0,
                    ]
                );
            }

            logger()->info('Successfully fetched and stored jobs.');
        } else {
            logger()->error('Failed to fetch jobs from Google API', ['response' => $response->body()]);
        }
    } catch (\Exception $e) {
        logger()->error('Error fetching jobs from Google API', ['error' => $e->getMessage()]);
    }
}

}