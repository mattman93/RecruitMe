<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FetchJobsFromHiringCafe implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    private $dataSource;
    private $jobsCollected = 0;
    private $duplicatesSkipped = 0;
    private $errorsEncountered = 0;
    private $currentJobTitle = '';

    public function __construct(){}

    public function handle(): void
    {
        Log::info('Starting FetchJobsFromHiringCafe job');

        // Jitter is now handled by the scheduler, not the job
        Log::info("Starting job execution immediately");

        try {
            // Get the hiring.cafe data source
            $this->dataSource = DataSource::where('name', 'hiring.cafe')
                ->where('is_active', true)
                ->first();

            if (!$this->dataSource) {
                Log::error('hiring.cafe data source not found or inactive');
                $this->sendErrorEmail('Data source not found', 'hiring.cafe data source is not configured or inactive');
                return;
            }

            // Check rate limits
            if (!$this->dataSource->withinRateLimit()) {
                Log::info('Rate limit exceeded for hiring.cafe, skipping this run');
                $this->sendRateLimitEmail();
                return;
            }

            // Fetch jobs with pagination
            $this->fetchAllJobs();

            // Update data source statistics
            $this->dataSource->updateFetchStats($this->jobsCollected);

            // Send email with results (success or partial success)
            $this->sendCompletionEmail();

            Log::info("FetchJobsFromHiringCafe completed. Jobs collected: {$this->jobsCollected}, Duplicates skipped: {$this->duplicatesSkipped}, Errors: {$this->errorsEncountered}");

        } catch (\Exception $e) {
            Log::error('FetchJobsFromHiringCafe failed: ' . $e->getMessage());
            $this->sendErrorEmail('Job execution failed', $e->getMessage());
            throw $e;
        }
    }

    private function fetchAllJobs(): void
    {
        $page = 0;
        $maxPages = 10; // Prevent infinite loops
        $batchSize = 40; // Based on API response structure

        do {
            $hasMoreJobs = $this->fetchJobsBatch($page, $batchSize);
            $page++;
        } while ($hasMoreJobs && $page < $maxPages);
    }

    private function fetchJobsBatch(int $page, int $size): bool
    {
        $payload = $this->buildRequestPayload($page, $size);
        
        Log::info("Fetching jobs batch - Page: {$page}, Size: {$size}");

        $response = Http::withHeaders($this->dataSource->headers)
            ->timeout(30)
            ->post($this->dataSource->url, $payload);

        // Handle 4xx/5xx responses immediately
        if ($response->status() >= 400) {
            $errorMessage = "HTTP {$response->status()} error from hiring.cafe API";
            Log::error($errorMessage . ': ' . $response->body());
            $this->sendErrorEmail('API Error - Potential IP Block', $errorMessage);
            
            // Stop the job immediately on client/server errors
            throw new \Exception($errorMessage);
        }

        if (!$response->successful()) {
            $this->errorsEncountered++;
            Log::warning("API request failed with status: {$response->status()}");
            return false;
        }

        $data = $response->json();
        
        // Save raw response as JSON file
        $this->saveRawResponse($data, $page);

        // Process and store jobs
        if (isset($data['results']) && is_array($data['results'])) {
            $this->processJobs($data['results']);
            
            // Check if there are more pages
            return count($data['results']) >= $size;
        }

        return false;
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
            'company' => $companyData['name'] ?? 'Unknown', // FIX: Use company data
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
            'company_tagline' => $this->cleanAndEncodeText($companyData['tagline']), // FIX: Clean UTF-8 encoding issues
            'company_founded_year' => $companyData['year_founded'] ?? null,
            'company_funding_series' => $companyData['latest_investment_series'] ?? null,
            'company_investors' => json_encode($companyData['investors'] ?? []),
            'company_headquarters_country' => $companyData['headquarters_country'] ?? null,

            // Benefits and perks (FIX: Use correct field names)
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
            'data_quality_score' => 'high', // hiring.cafe typically has high quality data
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
        // Try to find publish date in various formats
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
        $filename = "hiring_cafe_responses/{$timestamp}_page_{$page}.json";
        
        Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT));
        Log::info("Saved raw response to: {$filename}");
    }

    private function sendCompletionEmail(): void
    {
        $totalJobs = Lead::count();
        $hasErrors = $this->errorsEncountered > 0;
        
        $statusEmoji = $hasErrors ? '⚠️' : '✅';
        $statusText = $hasErrors ? 'Completed with Errors' : 'Successfully Completed';
        
        $subject = "{$statusEmoji} Hiring.cafe Job Fetch {$statusText} - {$this->jobsCollected} jobs collected";
        $message = "
            <h2>{$statusEmoji} Hiring.cafe Job Fetch Report</h2>
            <p><strong>Status:</strong> {$statusText}</p>
            <p><strong>Search Term Used:</strong> {$this->currentJobTitle}</p>
            <p><strong>Jobs collected this run:</strong> {$this->jobsCollected}</p>
            <p><strong>Duplicates skipped:</strong> {$this->duplicatesSkipped}</p>
            " . ($hasErrors ? "<p><strong>⚠️ Errors encountered:</strong> {$this->errorsEncountered} (check logs for details)</p>" : "") . "
            <p><strong>Total jobs in database:</strong> {$totalJobs}</p>
            <p><strong>Timestamp:</strong> " . now()->format('Y-m-d H:i:s T') . "</p>
            
            <hr>
            <p><em>This is an automated message from AppliFlow job collection system.</em></p>
        ";

        $this->sendEmail('mattcieslak93@gmail.com', $subject, $message);
    }

    private function sendRateLimitEmail(): void
    {
        $nextAllowedTime = $this->dataSource->last_fetched_at?->addHour()?->format('H:i T') ?? 'Unknown';
        
        $subject = "⏱️ Hiring.cafe Job Fetch - Rate Limited";
        $message = "
            <h2>⏱️ Hiring.cafe Job Fetch Report</h2>
            <p><strong>Status:</strong> Rate Limited - Skipped</p>
            <p><strong>Reason:</strong> API rate limit not yet reset</p>
            <p><strong>Last successful fetch:</strong> " . ($this->dataSource->last_fetched_at?->format('Y-m-d H:i:s T') ?? 'Never') . "</p>
            <p><strong>Next attempt allowed after:</strong> {$nextAllowedTime}</p>
            <p><strong>Timestamp:</strong> " . now()->format('Y-m-d H:i:s T') . "</p>
            
            <hr>
            <p><em>This is normal behavior to prevent API abuse. The job will successfully run when the rate limit resets.</em></p>
            <p><em>This is an automated message from AppliFlow job collection system.</em></p>
        ";

        $this->sendEmail('mattcieslak93@gmail.com', $subject, $message);
    }

    private function sendSuccessEmail(): void
    {
        // Legacy method - now handled by sendCompletionEmail
        $this->sendCompletionEmail();
    }

    private function sendErrorEmail(string $errorType, string $errorMessage): void
    {
        $subject = "ALERT: Hiring.cafe Job Fetch Error - {$errorType}";
        $message = "
            <h2>⚠️ Hiring.cafe Job Fetch Error</h2>
            <p><strong>Error Type:</strong> {$errorType}</p>
            <p><strong>Error Message:</strong> {$errorMessage}</p>
            <p><strong>Jobs collected before error:</strong> {$this->jobsCollected}</p>
            <p><strong>Timestamp:</strong> " . now()->format('Y-m-d H:i:s T') . "</p>
            
            <hr>
            <p><em>Please check the application logs and API status. Job collection has been stopped.</em></p>
        ";

        $this->sendEmail('mattcieslak93@gmail.com', $subject, $message);
    }

    private function sendEmail(string $to, string $subject, string $message): void
    {
        try {
            Mail::raw(strip_tags($message), function ($mail) use ($to, $subject, $message) {
                $mail->to($to)
                     ->subject($subject)
                     ->html($message);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * Clean and truncate text field with proper encoding
     */
    private function truncateAndClean(?string $text, int $maxLength): ?string
    {
        if (empty($text)) {
            return null;
        }
        
        // Strip HTML tags and clean encoding
        $cleaned = strip_tags($text);
        $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
        
        // Truncate if too long
        if (mb_strlen($cleaned) > $maxLength) {
            $cleaned = mb_substr($cleaned, 0, $maxLength - 3) . '...';
        }
        
        return $cleaned;
    }

    /**
     * Clean text with proper UTF-8 encoding and remove problematic characters
     */
    private function cleanAndEncodeText(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }
        
        // Strip HTML tags first
        $cleaned = strip_tags($text);
        
        // Remove or replace problematic UTF-8 sequences
        $cleaned = preg_replace('/\x{E2}\x{80}[\x{90}-\x{9F}]/u', ' ', $cleaned); // Replace em dashes, en dashes, etc.
        $cleaned = preg_replace('/\x{E2}\x{80}[\x{A0}-\x{AF}]/u', '"', $cleaned); // Replace smart quotes
        $cleaned = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}]/u', '', $cleaned); // Remove control chars
        
        // Ensure proper UTF-8 encoding
        $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
        
        // Truncate to reasonable length for taglines (being conservative)
        if (mb_strlen($cleaned) > 400) {
            $cleaned = mb_substr($cleaned, 0, 397) . '...';
        }
        
        return trim($cleaned) ?: null;
    }
}
