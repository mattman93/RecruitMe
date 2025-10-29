<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\Lead;
use App\Models\SchedulerRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PlaywrightFetchJobsFromHiringCafe implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 minutes timeout (browser automation is slower)
    public $tries = 2;

    private $dataSource;
    private $jobsCollected = 0;
    private $duplicatesSkipped = 0;
    private $errorsEncountered = 0;
    private $currentJobTitle = '';
    private $schedulerRun = null;

    public function __construct(){}

    public function handle(): void
    {
        Log::info('Starting PlaywrightFetchJobsFromHiringCafe job');

        // Create scheduler run record
        $this->schedulerRun = SchedulerRun::create([
            'job_name' => 'PlaywrightFetchJobsFromHiringCafe',
            'status' => 'success',
            'started_at' => now(),
        ]);

        try {
            // Get the hiring.cafe data source
            $this->dataSource = DataSource::where('name', 'hiring.cafe')
                ->where('is_active', true)
                ->first();

            if (!$this->dataSource) {
                Log::error('hiring.cafe data source not found or inactive');
                $this->sendErrorEmail('Data source not found', 'hiring.cafe data source is not configured or inactive');
                $this->schedulerRun->update([
                    'status' => 'failed',
                    'error_message' => 'Data source not found',
                    'completed_at' => now(),
                ]);
                return;
            }

            // Check rate limits
            if (!$this->dataSource->withinRateLimit()) {
                Log::info('Rate limit exceeded for hiring.cafe, skipping this run');
                $this->sendRateLimitEmail();
                $this->schedulerRun->update([
                    'status' => 'rate_limited',
                    'completed_at' => now(),
                ]);
                return;
            }

            // Fetch jobs with pagination
            $this->fetchAllJobs();

            // Update data source statistics
            $this->dataSource->updateFetchStats($this->jobsCollected);

            // Send email with results (success or partial success)
            $this->sendCompletionEmail();

            // Update scheduler run with final stats
            $this->schedulerRun->update([
                'status' => $this->errorsEncountered > 0 ? 'failed' : 'success',
                'search_term_used' => $this->currentJobTitle,
                'jobs_collected' => $this->jobsCollected,
                'duplicates_skipped' => $this->duplicatesSkipped,
                'errors_encountered' => $this->errorsEncountered,
                'total_jobs_in_db' => Lead::count(),
                'completed_at' => now(),
            ]);

            Log::info("PlaywrightFetchJobsFromHiringCafe completed. Jobs collected: {$this->jobsCollected}, Duplicates skipped: {$this->duplicatesSkipped}, Errors: {$this->errorsEncountered}");

        } catch (\Exception $e) {
            Log::error('PlaywrightFetchJobsFromHiringCafe failed: ' . $e->getMessage());
            $this->sendErrorEmail('Job execution failed', $e->getMessage());

            // Update scheduler run with error
            if ($this->schedulerRun) {
                $this->schedulerRun->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'jobs_collected' => $this->jobsCollected,
                    'duplicates_skipped' => $this->duplicatesSkipped,
                    'errors_encountered' => $this->errorsEncountered,
                    'completed_at' => now(),
                ]);
            }

            throw $e;
        }
    }

    private function fetchAllJobs(): void
    {
        $maxBatches = 3; // Maximum batches to fetch per run (increased from 1)
        $batchSize = 40; // Based on API response structure
        $maxEstimatedPages = 10; // Estimate total pages available on hiring.cafe (increased from 3)

        // Generate random pages to fetch for better discovery
        $randomPages = $this->generateRandomPages($maxBatches, $maxEstimatedPages);

        Log::info("Fetching from randomized pages: " . implode(', ', $randomPages));

        foreach ($randomPages as $page) {
            $hasJobs = $this->fetchJobsBatch($page, $batchSize);

            // If a random page returns no jobs, it might be beyond the available data
            // but continue with other pages as they might have jobs
            if (!$hasJobs) {
                Log::info("No jobs found on page {$page}, continuing with other pages");
            }

            // Add delay between requests to be respectful to the API
            sleep(1); // 1 second delay between batches (reduced from 2)
        }
    }

    /**
     * Generate an array of random page numbers to fetch
     */
    private function generateRandomPages(int $maxBatches, int $maxEstimatedPages): array
    {
        // Generate unique random page numbers
        $pages = [];
        $attempts = 0;
        $maxAttempts = $maxBatches * 3; // Prevent infinite loops

        while (count($pages) < $maxBatches && $attempts < $maxAttempts) {
            $randomPage = random_int(1, $maxEstimatedPages);

            // Ensure we don't duplicate pages
            if (!in_array($randomPage, $pages)) {
                $pages[] = $randomPage;
            }

            $attempts++;
        }

        // Sort pages for consistent logging
        sort($pages);

        return $pages;
    }

    private function fetchJobsBatch(int $page, int $size): bool
    {
        $payload = $this->buildRequestPayload($page, $size);

        Log::info("Fetching jobs batch via Playwright - Page: {$page}, Size: {$size}");

        try {
            $result = $this->executePlaywrightRequest($payload);

            if (isset($result['error']) && $result['error']) {
                $errorMessage = $result['message'] ?? 'Unknown error from Playwright script';
                Log::error("Playwright error: {$errorMessage}");
                $this->errorsEncountered++;
                return false;
            }

            if (!isset($result['status']) || $result['status'] >= 400) {
                $status = $result['status'] ?? 'unknown';
                $errorMessage = "HTTP {$status} error from hiring.cafe API";
                Log::error($errorMessage);
                $this->errorsEncountered++;
                return false;
            }

            $data = $result['data'] ?? [];

            // Save raw response as JSON file
            $this->saveRawResponse($data, $page);

            // Process and store jobs
            if (isset($data['results']) && is_array($data['results'])) {
                $this->processJobs($data['results']);

                // Check if there are more pages
                return count($data['results']) >= $size;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to execute Playwright request: ' . $e->getMessage());
            $this->errorsEncountered++;
            return false;
        }
    }

    private function executePlaywrightRequest(array $payload): array
    {
        $scriptPath = base_path('scripts/fetch-hiring-cafe.js');
        $url = $this->dataSource->url;
        $headers = $this->dataSource->headers;

        // Escape JSON for shell
        $headersJson = escapeshellarg(json_encode($headers));
        $payloadJson = escapeshellarg(json_encode($payload));

        // Set Playwright browsers path to accessible location for www-data user
        $browsersPath = base_path('.cache');
        $command = "PLAYWRIGHT_BROWSERS_PATH={$browsersPath} node {$scriptPath} {$url} {$headersJson} {$payloadJson} 2>&1";

        Log::info("Executing Playwright script");

        $output = shell_exec($command);

        if (empty($output)) {
            throw new \Exception('No output from Playwright script');
        }

        // Parse JSON output
        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse Playwright output: ' . $output);
            throw new \Exception('Invalid JSON from Playwright script: ' . json_last_error_msg());
        }

        return $result;
    }

    private function buildRequestPayload(int $page, int $size): array
    {
        $defaultPayload = $this->dataSource->custom_data['default_payload'];
        $defaultPayload['size'] = $size;
        $defaultPayload['page'] = $page;

        // Rotate through job titles to get diverse results
        $jobTitle = $this->getRotatingJobTitle();
        $defaultPayload['searchState']['jobTitleQuery'] = $jobTitle;

        Log::info("Using job title for search: {$jobTitle}");

        return $defaultPayload;
    }

    private function getRotatingJobTitle(): string
    {
        $jobTitles = $this->dataSource->custom_data['job_titles'] ?? ['Software Engineer'];

        // Use current hour to determine which job title to use
        // This ensures we cycle through different titles throughout the day
        $currentHour = now()->hour;
        $titleIndex = $currentHour % count($jobTitles);

        $this->currentJobTitle = $jobTitles[$titleIndex];
        return $this->currentJobTitle;
    }

    private function processJobs(array $jobs): void
    {
        foreach ($jobs as $jobData) {
            try {
                if ($this->isJobAlreadyExists($jobData)) {
                    $this->duplicatesSkipped++;
                    continue;
                }

                $this->createLead($jobData);
                $this->jobsCollected++;

            } catch (\Exception $e) {
                $this->errorsEncountered++;
                Log::error('Failed to process job: ' . $e->getMessage(), ['job_id' => $jobData['id'] ?? 'unknown']);
            }
        }
    }

    private function isJobAlreadyExists(array $jobData): bool
    {
        return Lead::where('external_id', $jobData['id'] ?? '')
            ->where('source_platform', 'hiring.cafe')
            ->exists();
    }

    private function createLead(array $jobData): void
    {
        $jobInfo = $jobData['job_information'] ?? [];
        $processedData = $jobData['v5_processed_job_data'] ?? [];
        $companyData = $jobData['v5_processed_company_data'] ?? [];

        Lead::create([
            // Original fields
            'job_title' => $jobInfo['title'] ?? 'Unknown',
            'company' => $companyData['name'] ?? 'Unknown',
            'pay_range' => $this->formatPayRange($processedData),
            'description' => strip_tags($jobInfo['description'] ?? ''),
            'location' => $this->truncateAndClean($processedData['formatted_workplace_location'], 1000),
            'employment_type' => implode(', ', $processedData['commitment'] ?? ['Full-time']),
            'experience_level' => $processedData['seniority_level'] ?? null,
            'source_url' => $jobData['apply_url'] ?? null,
            'is_active' => !($jobData['is_expired'] ?? false),

            // Job identification and source
            'external_id' => $jobData['id'],
            'source_platform' => 'hiring.cafe',
            'board_token' => $jobData['board_token'] ?? null,
            'apply_url' => $jobData['apply_url'] ?? null,

            // Enhanced job information
            'core_job_title' => $processedData['core_job_title'] ?? null,
            'job_title_raw' => $jobInfo['job_title_raw'] ?? $jobInfo['title'],
            'requirements_summary' => $processedData['requirements_summary'] ?? null,
            'technical_tools' => json_encode($processedData['technical_tools'] ?? []),
            'job_category' => $processedData['job_category'] ?? null,
            'seniority_level' => $processedData['seniority_level'] ?? null,
            'role_type' => $processedData['role_type'] ?? null,

            // Enhanced compensation
            'yearly_min_compensation' => $processedData['yearly_min_compensation'],
            'yearly_max_compensation' => $processedData['yearly_max_compensation'],
            'hourly_min_compensation' => $processedData['hourly_min_compensation'],
            'hourly_max_compensation' => $processedData['hourly_max_compensation'],
            'listed_compensation_currency' => $processedData['listed_compensation_currency'] ?? 'USD',
            'listed_compensation_frequency' => $processedData['listed_compensation_frequency'] ?? null,
            'is_compensation_transparent' => !empty($processedData['yearly_min_compensation']) || !empty($processedData['hourly_min_compensation']),

            // Work arrangements and location
            'workplace_type' => $processedData['workplace_type'] ?? null,
            'workplace_countries' => json_encode($processedData['workplace_countries'] ?? []),
            'workplace_states' => json_encode($processedData['workplace_states'] ?? []),
            'workplace_cities' => json_encode($processedData['workplace_cities'] ?? []),
            'formatted_workplace_location' => $this->truncateAndClean($processedData['formatted_workplace_location'], 500),

            // Requirements and qualifications
            'min_industry_and_role_yoe' => $processedData['min_industry_and_role_yoe'],
            'bachelors_degree_requirement' => $processedData['bachelors_degree_requirement'] ?? 'Not Mentioned',
            'masters_degree_requirement' => $processedData['masters_degree_requirement'] ?? 'Not Mentioned',
            'doctorate_degree_requirement' => $processedData['doctorate_degree_requirement'] ?? 'Not Mentioned',
            'licenses_or_certifications' => json_encode($processedData['licenses_or_certifications'] ?? []),

            // Company information
            'company_website' => $companyData['website'] ?? null,
            'company_linkedin_url' => $companyData['linkedin_url'] ?? null,
            'company_size' => $companyData['num_employees'] ?? null,
            'company_industries' => json_encode($companyData['industries'] ?? []),
            'company_tagline' => $this->cleanAndEncodeText($companyData['tagline']),
            'company_founded_year' => $companyData['year_founded'] ?? null,
            'company_funding_series' => $companyData['latest_investment_series'] ?? null,
            'company_investors' => json_encode($companyData['investors'] ?? []),
            'company_headquarters_country' => $companyData['headquarters_country'] ?? null,

            // Benefits and perks
            'retirement_plan' => $processedData['401k_matching'] ?? false,
            'generous_parental_leave' => $processedData['generous_parental_leave'] ?? false,
            'visa_sponsorship' => $processedData['visa_sponsorship'] ?? false,
            'relocation_assistance' => $processedData['relocation_assistance'] ?? false,
            'remote_work_available' => ($processedData['workplace_type'] === 'Remote'),
            'tuition_reimbursement' => $processedData['tuition_reimbursement'] ?? false,
            'generous_paid_time_off' => $processedData['generous_paid_time_off'] ?? false,

            // Work environment details
            'physical_environment' => $processedData['workplace_physical_environment'] ?? null,
            'oral_communication_level' => $processedData['oral_communication_level'] ?? null,
            'physical_labor_intensity' => $processedData['physical_labor_intensity'] ?? null,
            'computer_usage' => $processedData['computer_usage'] ?? null,
            'cognitive_demand' => $processedData['cognitive_demand'] ?? null,
            'security_clearance' => $processedData['security_clearance'] ?? null,

            // Data quality and tracking
            'estimated_publish_date' => $this->parsePublishDate($processedData),
            'is_expired' => $jobData['is_expired'] ?? false,
            'data_quality_score' => 'high',
            'last_scraped_at' => now(),
            'requisition_id' => $jobData['requisition_id'] ?? null,
            'collapse_key' => $jobData['collapse_key'] ?? null,
        ]);
    }

    private function formatPayRange(array $processedData): string
    {
        $min = $processedData['yearly_min_compensation'] ?? null;
        $max = $processedData['yearly_max_compensation'] ?? null;

        if ($min && $max) {
            return '$' . number_format($min) . ' - $' . number_format($max);
        }

        return 'Not specified';
    }

    private function parsePublishDate(array $processedData): ?string
    {
        if (isset($processedData['estimated_publish_date_millis'])) {
            try {
                return Carbon::createFromTimestamp($processedData['estimated_publish_date_millis'] / 1000)->toDateTimeString();
            } catch (\Exception $e) {
                Log::warning('Failed to parse publish date from millis: ' . $e->getMessage());
            }
        }

        if (isset($processedData['estimated_publish_date'])) {
            try {
                return Carbon::parse($processedData['estimated_publish_date'])->toDateTimeString();
            } catch (\Exception $e) {
                Log::warning('Failed to parse publish date: ' . $e->getMessage());
            }
        }

        return null;
    }

    private function saveRawResponse(array $data, int $page): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "hiring_cafe_responses/{$timestamp}_page_{$page}_playwright.json";

        Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT));
        Log::info("Saved raw response to: {$filename}");
    }

    private function sendCompletionEmail(): void
    {
        $totalJobs = Lead::count();
        $hasErrors = $this->errorsEncountered > 0;

        $statusEmoji = $hasErrors ? '⚠️' : '✅';
        $statusText = $hasErrors ? 'Completed with Errors' : 'Successfully Completed';

        Log::info("{$statusEmoji} Hiring.cafe Job Fetch (Playwright) {$statusText}", [
            'status' => $statusText,
            'search_term' => $this->currentJobTitle,
            'jobs_collected' => $this->jobsCollected,
            'duplicates_skipped' => $this->duplicatesSkipped,
            'errors_encountered' => $this->errorsEncountered,
            'total_jobs_in_db' => $totalJobs
        ]);
    }

    private function sendRateLimitEmail(): void
    {
        $nextAllowedTime = $this->dataSource->last_fetched_at?->copy()->addHour()?->format('H:i T') ?? 'Unknown';

        Log::info('⏱️ Hiring.cafe Job Fetch (Playwright) - Rate Limited', [
            'status' => 'Rate Limited - Skipped',
            'last_fetch' => $this->dataSource->last_fetched_at?->format('Y-m-d H:i:s T') ?? 'Never',
            'next_allowed' => $nextAllowedTime
        ]);
    }

    private function sendErrorEmail(string $errorType, string $errorMessage): void
    {
        Log::error("ALERT: Hiring.cafe Job Fetch (Playwright) Error - {$errorType}", [
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'jobs_collected_before_error' => $this->jobsCollected
        ]);

        if (in_array($errorType, ['Data source not found', 'API Error - Potential IP Block'])) {
            $subject = "ALERT: Hiring.cafe Job Fetch (Playwright) Error - {$errorType}";
            $message = "
                <h2>⚠️ Hiring.cafe Job Fetch Error (Playwright)</h2>
                <p><strong>Error Type:</strong> {$errorType}</p>
                <p><strong>Error Message:</strong> {$errorMessage}</p>
                <p><strong>Jobs collected before error:</strong> {$this->jobsCollected}</p>
                <p><strong>Timestamp:</strong> " . now()->format('Y-m-d H:i:s T') . "</p>

                <hr>
                <p><em>Please check the application logs and API status. Job collection has been stopped.</em></p>
            ";

            $this->sendEmail('mattcieslak93@gmail.com', $subject, $message);
        }
    }

    private function sendEmail(string $to, string $subject, string $message): void
    {
        try {
            Log::info("Attempting to send email to: {$to}, Subject: {$subject}");

            Mail::raw(strip_tags($message), function ($mail) use ($to, $subject, $message) {
                $mail->to($to)
                     ->subject($subject)
                     ->html($message);
            });

            Log::info("Email sent successfully to: {$to}");
        } catch (\Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());
        }
    }

    private function truncateAndClean(?string $text, int $maxLength): ?string
    {
        if (empty($text)) {
            return null;
        }

        $cleaned = strip_tags($text);
        $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');

        if (mb_strlen($cleaned) > $maxLength) {
            $cleaned = mb_substr($cleaned, 0, $maxLength - 3) . '...';
        }

        return $cleaned;
    }

    private function cleanAndEncodeText(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        $cleaned = strip_tags($text);
        $cleaned = preg_replace('/\x{E2}\x{80}[\x{90}-\x{9F}]/u', ' ', $cleaned);
        $cleaned = preg_replace('/\x{E2}\x{80}[\x{A0}-\x{AF}]/u', '"', $cleaned);
        $cleaned = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}]/u', '', $cleaned);
        $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');

        if (mb_strlen($cleaned) > 400) {
            $cleaned = mb_substr($cleaned, 0, 397) . '...';
        }

        return trim($cleaned) ?: null;
    }
}
