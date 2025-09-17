<?php

namespace App\Console\Commands;

use App\Jobs\ProcessJobApplicationsBatch;
use App\Models\Lead;
use App\Models\User;
use App\Services\JobApplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestApplicationFlow extends Command
{
    protected $signature = 'test:application-flow 
                           {--user_id=3 : User ID to test with}
                           {--count=3 : Number of applications to test}
                           {--method=semi_auto : Application method}';

    protected $description = 'Test the complete job application flow including batch processing';

    public function handle()
    {
        $userId = $this->option('user_id');
        $count = $this->option('count');
        $method = $this->option('method');

        $this->info("🧪 Testing Complete Application Flow");
        $this->info("User ID: {$userId}, Count: {$count}, Method: {$method}");
        $this->newLine();

        // Get user
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        // Get some leads to test with
        $leads = Lead::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit($count)
            ->get();

        if ($leads->count() < $count) {
            $this->warn("Only found {$leads->count()} leads, using those");
        }

        $this->info("👤 User: {$user->name}");
        $this->info("📋 Testing with {$leads->count()} jobs:");
        foreach ($leads as $lead) {
            $this->line("  - {$lead->job_title} at {$lead->company}");
        }
        $this->newLine();

        // Queue applications using the service
        $applicationService = app(JobApplicationService::class);
        $applications = [];

        foreach ($leads as $lead) {
            try {
                $application = $applicationService->queueApplication($user, $lead);
                $application->update(['application_method' => $method]);
                $applications[] = $application;
                $this->info("✅ Queued: {$lead->job_title} at {$lead->company} (ID: {$application->id})");
            } catch (\Exception $e) {
                $this->error("❌ Failed to queue: {$lead->job_title} - {$e->getMessage()}");
            }
        }

        if (empty($applications)) {
            $this->error("No applications were queued successfully");
            return 1;
        }

        $this->newLine();
        
        // Start batch processing
        $applicationIds = array_column($applications, 'id');
        $batchId = 'test_batch_' . time() . '_' . $userId;
        
        $this->info("🚀 Starting batch processing (Batch ID: {$batchId})");
        
        // Dispatch the job
        ProcessJobApplicationsBatch::dispatch($userId, $applicationIds, $batchId);
        
        $this->info("✅ Batch job dispatched");
        $this->newLine();
        
        // Monitor progress
        $this->info("📊 Monitoring batch progress...");
        $this->monitorBatchProgress($batchId);
        
        return 0;
    }

    protected function monitorBatchProgress(string $batchId): void
    {
        $maxChecks = 30; // 30 checks = 1 minute max
        $checkCount = 0;
        
        while ($checkCount < $maxChecks) {
            $statusData = Cache::get("job_application_batch_{$batchId}");
            
            if (!$statusData) {
                $this->error("❌ Batch status not found");
                break;
            }
            
            $status = $statusData['status'];
            $completed = $statusData['completed'] ?? 0;
            $total = $statusData['total'] ?? 0;
            $successful = $statusData['successful'] ?? 0;
            $failed = $statusData['failed'] ?? 0;
            
            // Show current application being processed
            if ($statusData['current_application'] ?? null) {
                $current = $statusData['current_application'];
                $this->line("🔄 Processing: {$current['job_title']} at {$current['company']} ({$current['method']})");
            }
            
            // Show progress
            if ($total > 0) {
                $percentage = round(($completed / $total) * 100);
                $this->line("📈 Progress: {$completed}/{$total} ({$percentage}%) | ✅ {$successful} | ❌ {$failed}");
            }
            
            // Check if completed
            if ($status === 'completed' || $status === 'failed') {
                $this->newLine();
                if ($status === 'completed') {
                    $this->info("🎉 Batch processing completed!");
                    $this->info("📊 Final Results:");
                    $this->info("  - Total: {$total}");
                    $this->info("  - Successful: {$successful}");
                    $this->info("  - Failed: {$failed}");
                    $this->info("  - Success Rate: " . ($total > 0 ? round(($successful / $total) * 100) : 0) . "%");
                } else {
                    $this->error("❌ Batch processing failed");
                    if (isset($statusData['error'])) {
                        $this->error("Error: {$statusData['error']}");
                    }
                }
                break;
            }
            
            $checkCount++;
            sleep(2); // Wait 2 seconds between checks
        }
        
        if ($checkCount >= $maxChecks) {
            $this->warn("⏰ Monitoring timeout - batch may still be processing");
        }
    }
}