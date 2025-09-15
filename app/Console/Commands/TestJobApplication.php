<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Models\Lead;
use App\Models\User;
use App\Services\JobApplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestJobApplication extends Command
{
    protected $signature = 'test:job-application 
                           {--user_id=1 : User ID to test with}
                           {--job_url= : Specific job URL to test (optional)}
                           {--method=semi_auto : Application method (full_auto, semi_auto, iframe)}
                           {--dry-run : Just show what would happen without actually applying}';

    protected $description = 'Test the job application automation system';

    protected JobApplicationService $applicationService;

    public function __construct(JobApplicationService $applicationService)
    {
        parent::__construct();
        $this->applicationService = $applicationService;
    }

    public function handle()
    {
        $userId = $this->option('user_id');
        $jobUrl = $this->option('job_url');
        $method = $this->option('method');
        $dryRun = $this->option('dry-run');

        $this->info("🧪 Testing Job Application System");
        $this->info("User ID: {$userId}");
        $this->info("Method: {$method}");
        $this->info("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        $this->newLine();

        // Get user
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("👤 User: {$user->name} ({$user->email})");

        // Find a suitable job to test with
        $lead = $this->findTestJob($jobUrl);
        if (!$lead) {
            $this->error("No suitable job found for testing");
            return 1;
        }

        $this->info("💼 Testing with job: {$lead->job_title} at {$lead->company}");
        $this->info("🔗 URL: {$lead->source_url}");
        $this->newLine();

        // Analyze the application strategy
        $strategy = $this->applicationService->determineApplicationStrategy($lead);
        $this->info("🎯 Recommended Strategy: {$strategy['strategy']}");
        $this->info("📝 Reason: {$strategy['reason']}");
        
        if ($strategy['site_structure']) {
            $this->info("🏗️  Site Structure: {$strategy['site_structure']->platform_name}");
            $this->info("📊 Success Rate: {$strategy['site_structure']->success_rate}%");
        } else {
            $this->warn("⚠️  No site structure found - new site needs mapping");
        }
        $this->newLine();

        if ($dryRun) {
            $this->info("🔍 DRY RUN - Showing prepared data:");
            $formData = $this->applicationService->prepareApplicationData($user, $lead);
            $this->displayFormData($formData);
            return 0;
        }

        // Queue the application
        $this->info("📝 Queueing application...");
        try {
            $application = $this->applicationService->queueApplication($user, $lead);
            
            // Override method if specified
            if ($method !== 'semi_auto') {
                $application->update(['application_method' => $method]);
            }

            $this->info("✅ Application queued successfully (ID: {$application->id})");
            $this->newLine();

            // Process the application
            $this->info("🚀 Processing application...");
            $this->info("Method: {$application->application_method}");

            $success = $this->applicationService->processApplication($application);
            
            // Show results
            $application->refresh();
            $this->showApplicationResults($application, $success);

        } catch (\Exception $e) {
            $this->error("❌ Application failed: " . $e->getMessage());
            Log::error("Test application failed", [
                'user_id' => $userId,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    protected function findTestJob(?string $jobUrl): ?Lead
    {
        if ($jobUrl) {
            // Find job by URL
            return Lead::where('source_url', 'LIKE', "%{$jobUrl}%")
                ->where('is_active', true)
                ->first();
        }

        // Find an Ashby job for testing
        $ashbyJob = Lead::where('source_url', 'LIKE', '%jobs.ashbyhq.com%')
            ->where('is_active', true)
            ->first();

        if ($ashbyJob) {
            return $ashbyJob;
        }

        // Fall back to any active job
        return Lead::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    protected function displayFormData(array $formData): void
    {
        $this->info("Personal Info:");
        $this->line("  Name: " . ($formData['personal']['full_name'] ?? 'Not set'));
        $this->line("  Email: " . ($formData['personal']['email'] ?? 'Not set'));
        $this->line("  Phone: " . ($formData['personal']['phone'] ?? 'Not set'));
        $this->newLine();

        $this->info("Work Authorization:");
        $this->line("  Can work in US: " . ($formData['work_authorization']['can_work_in_us'] ? 'Yes' : 'No'));
        $this->line("  Needs visa sponsorship: " . ($formData['work_authorization']['requires_visa_sponsorship'] ? 'Yes' : 'No'));
        $this->newLine();

        $this->info("Resume:");
        $this->line("  File: " . ($formData['resume']['file_path'] ?? 'No resume uploaded'));
        $this->newLine();

        $experienceCount = count($formData['experience']);
        $this->info("Experience ({$experienceCount} jobs):");
        foreach (array_slice($formData['experience'], 0, 3) as $exp) {
            $this->line("  - {$exp['position']} at {$exp['company']}");
        }
    }

    protected function showApplicationResults(JobApplication $application, bool $success): void
    {
        $this->newLine();
        $this->info("📊 Application Results:");
        $this->line("Status: {$application->status}");
        $this->line("Duration: " . ($application->duration ?? 'N/A'));
        $this->line("Retry count: {$application->retry_count}");
        
        if ($application->final_application_url) {
            $this->line("Final URL: {$application->final_application_url}");
        }

        if ($application->confirmation_message) {
            $this->line("Confirmation: {$application->confirmation_message}");
        }

        if ($application->error_message) {
            $this->line("Error: {$application->error_message}");
        }

        $this->newLine();
        
        if ($application->automation_log) {
            $this->info("📝 Automation Log:");
            $logLines = explode("\n", $application->automation_log);
            foreach ($logLines as $line) {
                if (trim($line)) {
                    $this->line("  " . trim($line));
                }
            }
        }

        if ($application->screenshots) {
            $this->newLine();
            $this->info("📸 Screenshots taken:");
            foreach ($application->screenshots as $screenshot) {
                $this->line("  - {$screenshot}");
            }
        }

        $this->newLine();
        
        if ($success && in_array($application->status, ['form_filled', 'submitted'])) {
            $this->info("🎉 Test completed successfully!");
            
            if ($application->status === 'form_filled') {
                $this->warn("🔔 Application requires user confirmation to submit");
                $this->line("Visit: {$application->final_application_url}");
            }
        } else {
            $this->error("❌ Test failed or requires manual intervention");
        }
    }
}