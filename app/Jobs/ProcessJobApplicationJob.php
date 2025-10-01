<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\PlaywrightAutomationService;

class ProcessJobApplicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes timeout for the job

    protected $jobUrl;
    protected $userId;
    protected $userFormData;
    protected $sessionKey;

    public function __construct($jobUrl, $userId, $userFormData, $sessionKey)
    {
        $this->jobUrl = $jobUrl;
        $this->userId = $userId;
        $this->userFormData = $userFormData;
        $this->sessionKey = $sessionKey;
    }

    public function handle()
    {
        Log::info('Processing job application in background', [
            'job_url' => $this->jobUrl,
            'user_id' => $this->userId,
            'session_key' => $this->sessionKey
        ]);

        try {
            // Update status to processing
            Cache::put($this->sessionKey, [
                'status' => 'processing',
                'message' => 'Analyzing job posting and filling application...',
                'started_at' => now()->toISOString()
            ], 600); // 10 minutes

            // Use the CLI command approach since it works reliably
            $formDataJson = json_encode($this->userFormData);
            
            $startTime = microtime(true);
            $result = \Illuminate\Support\Facades\Process::timeout(300)->run([
                'php', 'artisan', 'job:process-application',
                $this->jobUrl,
                $this->userId,
                '--form-data=' . $formDataJson
            ]);
            $duration = round(microtime(true) - $startTime, 2);
            
            // Parse the result from CLI output regardless of exit code
            // CLI returns non-zero for needs_user_input, but that's still a valid result
            $output = $result->output();
            if (preg_match('/RESULT_START\s*(.*?)\s*RESULT_END/s', $output, $matches)) {
                $parsedResult = json_decode($matches[1], true);
                if (!$parsedResult) {
                    throw new \Exception('Failed to parse CLI result JSON');
                }
                $result = $parsedResult;
            } else {
                // If no result found and command failed, throw the actual error
                if (!$result->successful()) {
                    throw new \Exception('CLI command failed: ' . $result->errorOutput());
                } else {
                    throw new \Exception('CLI result not found in output');
                }
            }

            // Store the final result
            $finalResult = array_merge($result, [
                'duration' => $duration,
                'completed_at' => now()->toISOString()
            ]);

            Cache::put($this->sessionKey, $finalResult, 600); // 10 minutes

            // If the result contains a session_id, create a cross-reference mapping
            // so that the frontend can access the automation session
            if (isset($result['session_id'])) {
                $automationSessionKey = "session_{$result['session_id']}";
                
                // Check if automation session exists and extend its TTL
                $automationSession = Cache::get($automationSessionKey);
                if ($automationSession) {
                    // Extend the automation session TTL to match the result TTL
                    Cache::put($automationSessionKey, $automationSession, 600); // 10 minutes
                    
                    Log::info('Extended automation session TTL', [
                        'session_id' => $result['session_id'],
                        'automation_session_key' => $automationSessionKey
                    ]);
                } else {
                    Log::warning('Automation session not found for session_id', [
                        'session_id' => $result['session_id'],
                        'automation_session_key' => $automationSessionKey
                    ]);
                }
            }

            Log::info('Background job application completed', [
                'session_key' => $this->sessionKey,
                'status' => $result['status'],
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            Log::error('Background job application failed', [
                'session_key' => $this->sessionKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Store error result
            Cache::put($this->sessionKey, [
                'status' => 'error',
                'error' => $e->getMessage(),
                'completed_at' => now()->toISOString()
            ], 600);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Job failed completely', [
            'session_key' => $this->sessionKey,
            'error' => $exception->getMessage()
        ]);

        Cache::put($this->sessionKey, [
            'status' => 'error',
            'error' => 'Job processing failed: ' . $exception->getMessage(),
            'completed_at' => now()->toISOString()
        ], 600);
    }
}