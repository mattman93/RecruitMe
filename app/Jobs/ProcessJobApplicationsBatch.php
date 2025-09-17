<?php

namespace App\Jobs;

use App\Models\JobApplication;
use App\Services\JobApplicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessJobApplicationsBatch implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes per job
    public $tries = 1; // Don't retry automatically

    protected int $userId;
    protected array $applicationIds;
    protected string $batchId;

    public function __construct(int $userId, array $applicationIds, string $batchId = null)
    {
        $this->userId = $userId;
        $this->applicationIds = $applicationIds;
        $this->batchId = $batchId ?: 'batch_' . time() . '_' . $userId;
    }

    public function handle(JobApplicationService $applicationService): void
    {
        Log::info("Starting job applications batch processing", [
            'batch_id' => $this->batchId,
            'user_id' => $this->userId,
            'application_count' => count($this->applicationIds)
        ]);

        // Initialize batch status in cache
        $this->updateBatchStatus('processing', [
            'total' => count($this->applicationIds),
            'completed' => 0,
            'successful' => 0,
            'failed' => 0,
            'current_application' => null
        ]);

        $completed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($this->applicationIds as $applicationId) {
            try {
                $application = JobApplication::find($applicationId);
                
                if (!$application) {
                    Log::warning("Application not found", ['application_id' => $applicationId]);
                    $failed++;
                    continue;
                }

                // Update current application in status
                $this->updateCurrentApplication($application, $completed, $successful, $failed);

                // Process the application
                Log::info("Processing application", [
                    'application_id' => $applicationId,
                    'lead_id' => $application->lead_id,
                    'company' => $application->lead->company ?? 'Unknown'
                ]);

                $success = $applicationService->processApplication($application);
                
                if ($success) {
                    $successful++;
                    Log::info("Application processed successfully", ['application_id' => $applicationId]);
                } else {
                    $failed++;
                    Log::warning("Application processing failed", ['application_id' => $applicationId]);
                }

            } catch (\Exception $e) {
                $failed++;
                Log::error("Application processing error", [
                    'application_id' => $applicationId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Mark application as failed if not already updated
                if ($application ?? null) {
                    $application->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => now()
                    ]);
                }
            }

            $completed++;
            
            // Add small delay between applications to be respectful
            if ($completed < count($this->applicationIds)) {
                sleep(2);
            }
        }

        // Final status update
        $this->updateBatchStatus('completed', [
            'total' => count($this->applicationIds),
            'completed' => $completed,
            'successful' => $successful,
            'failed' => $failed,
            'current_application' => null,
            'completed_at' => now()->toDateTimeString()
        ]);

        Log::info("Job applications batch completed", [
            'batch_id' => $this->batchId,
            'total' => count($this->applicationIds),
            'successful' => $successful,
            'failed' => $failed
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job applications batch failed completely", [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->updateBatchStatus('failed', [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toDateTimeString()
        ]);
    }

    protected function updateBatchStatus(string $status, array $data): void
    {
        $statusData = array_merge([
            'batch_id' => $this->batchId,
            'user_id' => $this->userId,
            'status' => $status,
            'updated_at' => now()->toDateTimeString()
        ], $data);

        // Store in cache for 1 hour
        Cache::put("job_application_batch_{$this->batchId}", $statusData, 3600);
        
        // Also store user-specific key for easy lookup
        Cache::put("user_job_applications_{$this->userId}_latest_batch", $this->batchId, 3600);
    }

    protected function updateCurrentApplication(JobApplication $application, int $completed, int $successful, int $failed): void
    {
        $this->updateBatchStatus('processing', [
            'total' => count($this->applicationIds),
            'completed' => $completed,
            'successful' => $successful,
            'failed' => $failed,
            'current_application' => [
                'id' => $application->id,
                'job_title' => $application->lead->job_title ?? 'Unknown',
                'company' => $application->lead->company ?? 'Unknown',
                'method' => $application->application_method,
                'started_at' => now()->toDateTimeString()
            ]
        ]);
    }
}