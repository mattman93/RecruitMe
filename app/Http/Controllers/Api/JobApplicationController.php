<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Services\JobApplicationService;
use App\Services\PlaywrightAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobApplicationController extends Controller
{
    protected JobApplicationService $applicationService;
    protected PlaywrightAutomationService $playwrightService;

    public function __construct(JobApplicationService $applicationService, PlaywrightAutomationService $playwrightService)
    {
        $this->applicationService = $applicationService;
        $this->playwrightService = $playwrightService;
    }

    /**
     * Get all applications for the authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $applications = JobApplication::with(['lead', 'jobSiteStructure'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($applications);
    }

    /**
     * Queue applications for selected jobs
     */
    public function queueApplications(Request $request)
    {
        \Log::info('Queue applications request:', [
            'data' => $request->all(),
            'user_id' => Auth::id()
        ]);
        
        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'required|integer|exists:leads,id',
            'custom_responses' => 'array',
            'application_method' => 'string|in:auto,semi_auto,iframe,full_auto'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $leadIds = $request->get('lead_ids');
        $customResponses = $request->get('custom_responses', []);
        $preferredMethod = $request->get('application_method');

        $queuedApplications = [];
        $errors = [];

        foreach ($leadIds as $leadId) {
            try {
                $lead = Lead::findOrFail($leadId);
                
                // Check if user already applied to this job
                $existingApplication = JobApplication::where('user_id', $user->id)
                    ->where('lead_id', $leadId)
                    ->first();

                if ($existingApplication) {
                    $errors[] = "Already applied to {$lead->job_title} at {$lead->company}";
                    continue;
                }

                $application = $this->applicationService->queueApplication(
                    $user, 
                    $lead, 
                    $customResponses[$leadId] ?? []
                );

                // Override strategy if user specified a preference
                if ($preferredMethod) {
                    $application->update(['application_method' => $preferredMethod]);
                }

                $queuedApplications[] = $application->load(['lead', 'jobSiteStructure']);

            } catch (\Exception $e) {
                $errors[] = "Failed to queue application for lead {$leadId}: " . $e->getMessage();
            }
        }

        // If we have queued applications, start batch processing
        if (!empty($queuedApplications)) {
            $applicationIds = array_column($queuedApplications, 'id');
            $batchId = 'batch_' . time() . '_' . $user->id;
            
            // Generate a temporary access token for batch status polling
            $accessToken = 'token_' . bin2hex(random_bytes(16));
            
            // Store the access token mapping in cache (expires in 2 hours)
            \Illuminate\Support\Facades\Cache::put("batch_token_{$accessToken}", [
                'batch_id' => $batchId,
                'user_id' => $user->id,
                'expires_at' => now()->addHours(2)->toISOString()
            ], 120); // 2 hours in minutes
            
            // Create initial batch status in cache to prevent race condition
            \Illuminate\Support\Facades\Cache::put("job_application_batch_{$batchId}", [
                'batch_id' => $batchId,
                'user_id' => $user->id,
                'status' => 'queued',
                'updated_at' => now()->toDateTimeString(),
                'total' => count($queuedApplications),
                'completed' => 0,
                'successful' => 0,
                'failed' => 0,
                'current_application' => null
            ], 120); // 2 hours in minutes
            
            // Dispatch the batch processing job
            \App\Jobs\ProcessJobApplicationsBatch::dispatch($user->id, $applicationIds, $batchId);
            
            return response()->json([
                'success' => true,
                'queued_applications' => $queuedApplications,
                'batch_id' => $batchId,
                'access_token' => $accessToken,
                'errors' => $errors,
                'message' => count($queuedApplications) . ' applications queued and processing started',
                'status_endpoint' => "/api/applications/batch-status/{$batchId}?token={$accessToken}"
            ]);
        }

        return response()->json([
            'success' => false,
            'queued_applications' => [],
            'errors' => $errors,
            'message' => 'No applications were queued'
        ]);
    }

    /**
     * Process queued applications
     */
    public function processApplications(Request $request)
    {
        $user = Auth::user();
        
        $applications = JobApplication::where('user_id', $user->id)
            ->where('status', 'queued')
            ->limit($request->get('batch_size', 5))
            ->get();

        $results = [];

        foreach ($applications as $application) {
            try {
                $success = $this->applicationService->processApplication($application);
                $results[] = [
                    'application_id' => $application->id,
                    'success' => $success,
                    'status' => $application->fresh()->status,
                    'message' => $success ? 'Application processed successfully' : 'Application processing failed'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'application_id' => $application->id,
                    'success' => false,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'processed_count' => count($results)
        ]);
    }

    /**
     * Get application status and details
     */
    public function show($id)
    {
        $user = Auth::user();
        
        $application = JobApplication::with(['lead', 'jobSiteStructure'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'application' => $application,
            'automation_log_lines' => $application->automation_log ? 
                explode("\n", $application->automation_log) : [],
            'screenshots' => $application->screenshots ?? []
        ]);
    }

    /**
     * Update application status (for user confirmation)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:submitted,failed,manual_required',
            'confirmation_message' => 'string|nullable',
            'notes' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        $application = JobApplication::where('user_id', $user->id)
            ->findOrFail($id);

        $application->update([
            'status' => $request->get('status'),
            'confirmation_message' => $request->get('confirmation_message'),
            'completed_at' => in_array($request->get('status'), ['submitted', 'failed']) ? now() : null
        ]);

        // Add user notes to automation log
        if ($request->get('notes')) {
            $logEntry = "\n[" . now()->format('Y-m-d H:i:s') . "] User notes: " . $request->get('notes');
            $application->update([
                'automation_log' => ($application->automation_log ?? '') . $logEntry
            ]);
        }

        return response()->json([
            'success' => true,
            'application' => $application->fresh(),
            'message' => 'Application status updated successfully'
        ]);
    }

    /**
     * Get batch processing status
     */
    public function batchStatus($batchId)
    {
        \Log::info('Batch status request', [
            'batch_id' => $batchId,
            'user_authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'has_token' => request()->has('token')
        ]);
        
        // Check if token-based access is being used
        $token = request()->get('token');
        if ($token) {
            $tokenData = \Illuminate\Support\Facades\Cache::get("batch_token_{$token}");
            
            if (!$tokenData || $tokenData['batch_id'] !== $batchId) {
                \Log::warning('Batch status: Invalid or expired token', [
                    'batch_id' => $batchId,
                    'token' => $token,
                    'token_data' => $tokenData
                ]);
                return response()->json(['error' => 'Invalid or expired access token'], 401);
            }
            
            \Log::info('Batch status: Token-based access granted', [
                'batch_id' => $batchId,
                'user_id' => $tokenData['user_id']
            ]);
            
            // Use token-based access - skip session authentication
            $userId = $tokenData['user_id'];
        } else {
            // Fallback to session-based authentication
            $user = Auth::user();
            
            if (!$user) {
                \Log::warning('Batch status: User not authenticated', [
                    'batch_id' => $batchId,
                    'session_id' => request()->session()->getId(),
                    'has_session' => request()->hasSession()
                ]);
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            
            $userId = $user->id;
        }
        
        $statusData = \Illuminate\Support\Facades\Cache::get("job_application_batch_{$batchId}");
        
        \Log::info('Batch status cache lookup', [
            'batch_id' => $batchId,
            'cache_key' => "job_application_batch_{$batchId}",
            'found' => $statusData !== null,
            'data' => $statusData
        ]);
        
        if (!$statusData) {
            return response()->json([
                'error' => 'Batch not found or expired'
            ], 404);
        }
        
        // Verify the batch belongs to the authenticated user
        if ($statusData['user_id'] !== $userId) {
            \Log::warning('Batch status: User mismatch', [
                'expected_user_id' => $statusData['user_id'],
                'actual_user_id' => $userId
            ]);
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }
        
        return response()->json($statusData);
    }

    /**
     * Get application analytics
     */
    public function analytics(Request $request)
    {
        $user = Auth::user();
        $timeframe = $request->get('timeframe', '30'); // days

        $baseQuery = JobApplication::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($timeframe));

        $analytics = [
            'total_applications' => $baseQuery->count(),
            'status_breakdown' => $baseQuery->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'method_breakdown' => $baseQuery->selectRaw('application_method, COUNT(*) as count')
                ->groupBy('application_method')
                ->pluck('count', 'application_method'),
            'success_rate' => $this->calculateSuccessRate($baseQuery),
            'average_processing_time' => $this->calculateAverageProcessingTime($baseQuery),
            'applications_by_day' => $this->getApplicationsByDay($baseQuery),
            'top_companies' => $this->getTopCompanies($baseQuery)
        ];

        return response()->json($analytics);
    }

    /**
     * Calculate success rate
     */
    protected function calculateSuccessRate($query)
    {
        $total = $query->count();
        if ($total === 0) return 0;
        
        $successful = $query->where('status', 'submitted')->count();
        return round(($successful / $total) * 100, 1);
    }

    /**
     * Calculate average processing time
     */
    protected function calculateAverageProcessingTime($query)
    {
        $applications = $query->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_seconds')
            ->first();

        return $applications->avg_seconds ? round($applications->avg_seconds) : 0;
    }

    /**
     * Get applications grouped by day
     */
    protected function getApplicationsByDay($query)
    {
        return $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');
    }

    /**
     * Get top companies applied to
     */
    protected function getTopCompanies($query)
    {
        return $query->join('leads', 'job_applications.lead_id', '=', 'leads.id')
            ->selectRaw('leads.company, COUNT(*) as count')
            ->groupBy('leads.company')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'company');
    }

    /**
     * NEW: Process job application with Playwright + LLM (with timeout handling)
     */
    public function processApplication(Request $request)
    {
        // Enhanced logging for debugging
        \Log::info('JobApplicationController::processApplication called', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
            'user_agent' => $request->header('User-Agent'),
            'ip' => $request->ip()
        ]);
        
        $validator = Validator::make($request->all(), [
            'job_url' => 'required|url',
            'user_form_data' => 'array'
        ]);

        if ($validator->fails()) {
            \Log::warning('processApplication validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $jobUrl = $request->get('job_url');
        $userFormData = $request->get('user_form_data', []);

        \Log::info('Starting Playwright automation', [
            'job_url' => $jobUrl,
            'user_id' => $user->id,
            'user_form_data' => $userFormData,
            'domain' => parse_url($jobUrl, PHP_URL_HOST)
        ]);

        try {
            $startTime = microtime(true);
            
            // Set a reasonable max execution time for this request (45 seconds for UI)
            set_time_limit(45);
            
            // Use CLI command for consistent execution environment
            \Log::info('API using CLI execution for Playwright automation', [
                'job_url' => $jobUrl,
                'user_id' => $user->id,
                'user_agent' => request()->header('User-Agent'),
                'domain' => parse_url($jobUrl, PHP_URL_HOST)
            ]);
            
            // Execute via CLI command using Laravel's Process facade
            $formDataJson = json_encode($userFormData);
            
            \Log::info('Starting CLI command execution', [
                'command' => 'php artisan job:process-application',
                'job_url' => $jobUrl,
                'user_id' => $user->id
            ]);
            
            // Use Laravel's Process facade with timeout
            $processResult = \Illuminate\Support\Facades\Process::timeout(50) // 50 second timeout
                ->path(base_path())
                ->run([
                    'php',
                    'artisan',
                    'job:process-application',
                    $jobUrl,
                    $user->id,
                    '--form-data=' . $formDataJson
                ]);
            
            \Log::info('CLI command completed', [
                'successful' => $processResult->successful(),
                'failed' => $processResult->failed(),
                'exit_code' => $processResult->exitCode(),
                'output_length' => strlen($processResult->output()),
                'error_length' => strlen($processResult->errorOutput())
            ]);
            
            if ($processResult->failed()) {
                $errorMsg = $processResult->errorOutput() ?: 'CLI command failed';
                throw new \Exception("CLI automation failed: {$errorMsg}");
            }
            
            $output = $processResult->output();
            
            // Parse result from CLI output
            if (preg_match('/RESULT_START\s*(.*?)\s*RESULT_END/s', $output, $matches)) {
                $result = json_decode($matches[1], true);
                if ($result === null) {
                    throw new \Exception('Failed to parse CLI result: ' . $matches[1]);
                }
            } else {
                throw new \Exception('No valid result from CLI command. Output: ' . substr($output, 0, 500));
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            
            \Log::info('Playwright automation completed', [
                'job_url' => $jobUrl,
                'user_id' => $user->id,
                'status' => $result['status'],
                'duration' => $duration,
                'filled_fields' => $result['filled_fields'] ?? [],
                'session_id' => $result['session_id'] ?? null
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Playwright automation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_url' => $jobUrl,
                'user_id' => $user->id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => 'Automation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * NEW: Process job application asynchronously 
     */
    public function processApplicationAsync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_url' => 'required|url',
            'user_form_data' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $jobUrl = $request->get('job_url');
        $userFormData = $request->get('user_form_data', []);

        // Generate unique session key for tracking
        $sessionKey = 'job_automation_' . $user->id . '_' . time() . '_' . rand(1000, 9999);

        \Log::info('Starting async job application processing', [
            'session_key' => $sessionKey,
            'job_url' => $jobUrl,
            'user_id' => $user->id
        ]);

        // Store initial status
        \Illuminate\Support\Facades\Cache::put($sessionKey, [
            'status' => 'queued',
            'message' => 'Job queued for processing...',
            'queued_at' => now()->toISOString()
        ], 600); // 10 minutes

        // Dispatch the job
        \App\Jobs\ProcessJobApplicationJob::dispatch($jobUrl, $user->id, $userFormData, $sessionKey);

        return response()->json([
            'status' => 'queued',
            'session_key' => $sessionKey,
            'message' => 'Job application processing started',
            'status_url' => "/api/automation/status/{$sessionKey}"
        ]);
    }

    /**
     * Get status of async job application processing
     */
    public function getApplicationStatus($sessionKey)
    {
        $status = \Illuminate\Support\Facades\Cache::get($sessionKey);

        if (!$status) {
            return response()->json([
                'error' => 'Session not found or expired'
            ], 404);
        }

        return response()->json($status);
    }

    /**
     * NEW: Submit missing fields and continue automation
     */
    public function submitMissingFields(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'missing_field_values' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $sessionId = $request->get('session_id');
        $missingFieldValues = $request->get('missing_field_values');

        try {
            // Create a unique response session key for tracking this operation
            $responseSessionKey = 'missing_fields_' . $user->id . '_' . time() . '_' . rand(1000, 9999);
            
            \Log::info('Starting async missing fields submission', [
                'session_id' => $sessionId,
                'response_session_key' => $responseSessionKey,
                'user_id' => $user->id,
                'fields_count' => count($missingFieldValues)
            ]);
            
            // Create initial status in cache
            \Illuminate\Support\Facades\Cache::put($responseSessionKey, [
                'status' => 'queued',
                'message' => 'Preparing to submit missing fields...',
                'started_at' => now()->toISOString()
            ], 600); // 10 minutes
            
            // Dispatch the background job
            \App\Jobs\SubmitMissingFieldsJob::dispatch(
                $sessionId, 
                $missingFieldValues, 
                $user->id, 
                $responseSessionKey
            );
            
            return response()->json([
                'status' => 'processing',
                'message' => 'Missing fields submission started',
                'session_key' => $responseSessionKey,
                'polling_url' => "/api/automation/status/{$responseSessionKey}"
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to start missing fields submission:', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to start missing fields submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * NEW: Execute final application submission
     */
    public function submitApplication(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $sessionId = $request->get('session_id');

        try {
            $result = $this->playwrightService->executeApplicationSubmission(
                $sessionId, 
                $user->id
            );

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Failed to submit application:', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'status' => 'error',
                'error' => 'Application submission failed: ' . $e->getMessage()
            ], 500);
        }
    }
}