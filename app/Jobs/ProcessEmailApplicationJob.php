<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Lead;
use App\Models\User;
use App\Services\JobApplicationService;

class ProcessEmailApplicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout for email applications (contact discovery + sending)

    protected $leadId;
    protected $userId;
    protected $sessionKey;

    public function __construct($leadId, $userId, $sessionKey)
    {
        $this->leadId = $leadId;
        $this->userId = $userId;
        $this->sessionKey = $sessionKey;
    }

    public function handle()
    {
        Log::info('Processing email application in background', [
            'lead_id' => $this->leadId,
            'user_id' => $this->userId,
            'session_key' => $this->sessionKey
        ]);

        try {
            // Update status to processing
            Cache::put($this->sessionKey, [
                'status' => 'processing',
                'message' => 'Discovering contact information and preparing application...',
                'started_at' => now()->toISOString()
            ], 600); // 10 minutes

            // Load user and lead
            $user = User::findOrFail($this->userId);
            $lead = Lead::findOrFail($this->leadId);

            // Process the application
            $applicationService = app(JobApplicationService::class);
            $application = $applicationService->queueApplication($user, $lead);

            $startTime = microtime(true);
            $result = $applicationService->processApplication($application);
            $duration = round(microtime(true) - $startTime, 2);

            // Store the final result
            $finalResult = [
                'status' => $result ? 'submitted' : 'failed',
                'message' => $result ? 'Application submitted successfully' : 'Application failed',
                'application_id' => $application->id,
                'duration' => $duration,
                'completed_at' => now()->toISOString()
            ];

            Cache::put($this->sessionKey, $finalResult, 600); // 10 minutes

            Log::info('Background email application completed', [
                'session_key' => $this->sessionKey,
                'status' => $finalResult['status'],
                'application_id' => $application->id,
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            Log::error('Background email application failed', [
                'session_key' => $this->sessionKey,
                'lead_id' => $this->leadId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Store error result
            Cache::put($this->sessionKey, [
                'status' => 'error',
                'error' => 'Failed to process application: ' . $e->getMessage(),
                'completed_at' => now()->toISOString()
            ], 600);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Email application job failed completely', [
            'session_key' => $this->sessionKey,
            'lead_id' => $this->leadId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);

        Cache::put($this->sessionKey, [
            'status' => 'error',
            'error' => 'Job processing failed: ' . $exception->getMessage(),
            'completed_at' => now()->toISOString()
        ], 600);
    }
}
