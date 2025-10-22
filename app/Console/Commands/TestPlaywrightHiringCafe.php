<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use Illuminate\Console\Command;

class TestPlaywrightHiringCafe extends Command
{
    protected $signature = 'test:playwright-hiring-cafe';
    protected $description = 'Test Playwright script with HiringCafe API';

    public function handle()
    {
        $this->info('Testing Playwright HiringCafe script...');

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

        // Build a simple test payload - use a minimal payload to avoid errors
        $payload = [
            'page' => 0,
            'size' => 5,
            'searchState' => [
                'jobTitleQuery' => 'Software Engineer',
                'workplaceTypes' => ['Remote'],
                'dateFetchedPastNDays' => 30
            ]
        ];

        $this->info('Job title search: Software Engineer');

        // Prepare script execution
        $scriptPath = base_path('scripts/fetch-hiring-cafe.js');
        $url = $dataSource->url;
        $headers = $dataSource->headers;

        // Escape JSON for shell
        $headersJson = escapeshellarg(json_encode($headers));
        $payloadJson = escapeshellarg(json_encode($payload));

        // Execute the Node.js script
        $command = "node {$scriptPath} {$url} {$headersJson} {$payloadJson} 2>&1";

        $this->info('Executing Playwright script...');
        $this->info('Command: ' . $command);
        $this->newLine();

        $startTime = microtime(true);

        $output = shell_exec($command);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->info("Request completed in {$duration}ms");
        $this->newLine();

        if (empty($output)) {
            $this->error('No output from Playwright script');
            return 1;
        }

        // Parse JSON output
        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Failed to parse Playwright output:');
            $this->error($output);
            return 1;
        }

        if (isset($result['error']) && $result['error']) {
            $this->error('❌ Playwright Error');
            $this->error('Message: ' . ($result['message'] ?? 'Unknown error'));
            return 1;
        }

        if (isset($result['status'])) {
            $this->info('Status Code: ' . $result['status']);
            $this->info('Content-Type: ' . ($result['contentType'] ?? 'unknown'));

            if ($result['status'] === 429) {
                $this->error('❌ Still getting rate limited (429)');
                return 1;
            }

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $this->info('✅ Success!');

                $data = $result['data'] ?? [];

                if (isset($data['results'])) {
                    $this->info('Jobs returned: ' . count($data['results']));
                }

                if (isset($data['totalResults'])) {
                    $this->info('Total results available: ' . $data['totalResults']);
                }

                // Show first job title if available
                if (isset($data['results'][0]['job_information']['title'])) {
                    $this->newLine();
                    $this->info('First job: ' . $data['results'][0]['job_information']['title']);
                    $this->info('Company: ' . ($data['results'][0]['v5_processed_company_data']['name'] ?? 'Unknown'));
                }

                return 0;
            }

            $this->error('Request failed with status: ' . $result['status']);

            // Show error response if available
            $data = $result['data'] ?? [];
            if (isset($data['rawResponse'])) {
                $this->newLine();
                $this->error('Raw Response:');
                $this->error(substr($data['rawResponse'], 0, 1000)); // First 1000 chars
            } elseif (!empty($data)) {
                $this->newLine();
                $this->error('Response Data:');
                $this->error(json_encode($data, JSON_PRETTY_PRINT));
            }

            return 1;
        }

        $this->error('Unexpected response format');
        $this->error(json_encode($result, JSON_PRETTY_PRINT));

        return 1;
    }
}
