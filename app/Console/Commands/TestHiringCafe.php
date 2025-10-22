<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestHiringCafe extends Command
{
    protected $signature = 'test:hiring-cafe';
    protected $description = 'Test a single HTTP request to HiringCafe API';

    public function handle()
    {
        $this->info('Testing HiringCafe API request...');

        // Get the data source config
        $dataSource = DataSource::where('name', 'hiring.cafe')
            ->where('is_active', true)
            ->first();

        if (!$dataSource) {
            $this->error('hiring.cafe data source not found or inactive');
            return 1;
        }

        $this->info('Data source found: ' . $dataSource->name);
        $this->info('URL: ' . $dataSource->url);

        // Build a simple test payload
        $payload = $dataSource->custom_data['default_payload'] ?? [];
        $payload['size'] = 10; // Just fetch 10 jobs
        $payload['page'] = 1;

        // Get job titles and use the first one
        $jobTitles = $dataSource->custom_data['job_titles'] ?? ['Software Engineer'];
        $payload['searchState']['jobTitleQuery'] = $jobTitles[0];

        $this->info('Job title search: ' . $jobTitles[0]);
        $this->info('Making HTTP request...');

        try {
            $startTime = microtime(true);

            $response = Http::withHeaders($dataSource->headers)
                ->timeout(30)
                ->post($dataSource->url, $payload);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Display response info
            $this->info("Request completed in {$duration}ms");
            $this->info('Status Code: ' . $response->status());

            if ($response->status() === 429) {
                $this->error('❌ Rate Limited (429)');
                $this->error('Response body: ' . $response->body());

                // Check for rate limit headers
                $headers = $response->headers();
                if (isset($headers['Retry-After'])) {
                    $this->warn('Retry-After: ' . $headers['Retry-After'][0]);
                }
                if (isset($headers['X-RateLimit-Limit'])) {
                    $this->warn('Rate Limit: ' . $headers['X-RateLimit-Limit'][0]);
                }
                if (isset($headers['X-RateLimit-Remaining'])) {
                    $this->warn('Remaining: ' . $headers['X-RateLimit-Remaining'][0]);
                }
                if (isset($headers['X-RateLimit-Reset'])) {
                    $this->warn('Reset: ' . $headers['X-RateLimit-Reset'][0]);
                }

                return 1;
            }

            if ($response->successful()) {
                $this->info('✅ Success!');
                $data = $response->json();

                if (isset($data['results'])) {
                    $this->info('Jobs returned: ' . count($data['results']));
                }

                if (isset($data['totalResults'])) {
                    $this->info('Total results available: ' . $data['totalResults']);
                }

                // Show first job title if available
                if (isset($data['results'][0]['job_information']['title'])) {
                    $this->info('First job: ' . $data['results'][0]['job_information']['title']);
                }

                return 0;
            }

            $this->error('Request failed with status: ' . $response->status());
            $this->error('Response: ' . $response->body());

            return 1;

        } catch (\Exception $e) {
            $this->error('Exception occurred: ' . $e->getMessage());
            return 1;
        }
    }
}
